<?php

namespace Foodsharing\Modules\WallPost;

use Flourish\fImage;
use Foodsharing\Lib\Xhr\XhrResponses;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointTransactions;
use Foodsharing\Permissions\WallPostPermissions;

class WallPostXhr extends Control
{
    private string $table;
    private int $id;
    private readonly FoodSharePointTransactions $foodSharePointTransactions;
    private readonly WallPostGateway $wallPostGateway;
    private readonly WallPostPermissions $wallPostPermissions;

    public function __construct(
        FoodSharePointTransactions $foodSharePointTransactions,
        WallPostGateway $wallPostGateway,
        WallPostPermissions $wallPostPermissions,
        WallPostView $view,
    ) {
        $this->foodSharePointTransactions = $foodSharePointTransactions;
        $this->wallPostGateway = $wallPostGateway;
        $this->wallPostPermissions = $wallPostPermissions;
        $this->view = $view;

        parent::__construct();

        if ((int)$_GET['id'] > 0 && $this->wallPostGateway->isValidTarget($_GET['table'])) {
            $this->table = $_GET['table'];
            $this->id = (int)$_GET['id'];
            $this->view->setTable($this->table, $this->id);
        } else {
            echo '{status:0}';
        }
    }

    public function update()
    {
        if (!$this->wallPostPermissions->mayReadWall($this->session->id() ?? 0, $this->table, $this->id)) {
            return XhrResponses::PERMISSION_DENIED;
        }

        if ((int)$this->wallPostGateway->getLastPostId($this->table, $this->id) != (int)$_GET['last']) {
            if ($posts = $this->wallPostGateway->getPosts($this->table, $this->id)) {
                return [
                    'status' => 1,
                    'html' => $this->view->posts($posts, $this->wallPostPermissions->mayDeleteFromWall($this->session->id() ?? 0, $this->table, $this->id))
                ];
            }
        } else {
            return [
                'status' => 0
            ];
        }
    }

    public function post()
    {
        if (!$this->wallPostPermissions->mayWriteWall($this->session->id(), $this->table, $this->id)) {
            return XhrResponses::PERMISSION_DENIED;
        }

        $message = strip_tags((string)$_POST['text']);
        if (!(empty($message) && empty($_POST['attach']))) {
            $attach = null;
            if (!empty($_POST['attach'])) {
                $parts = explode(':', (string)$_POST['attach']);
                $attach = null;
                foreach ($parts as $p) {
                    $file = explode('-', $p);
                    if (!isset($attach[$file[0]])) {
                        $attach[$file[0]] = [];
                    }
                    $attach[$file[0]][] = [
                        'file' => $file[1]
                    ];
                }
                $attach = json_encode($attach) ?: null;
            }
            if ($this->wallPostGateway->addPost($message, $this->session->id(), $this->table, $this->id, $attach)) {
                if ($this->table === 'fairteiler') {
                    $this->foodSharePointTransactions->sendNewFoodSharePointPostNotifications($this->id);
                }

                return [
                    'status' => 1,
                    'html' => $this->view->posts(
                        $this->wallPostGateway->getPosts($this->table, $this->id),
                        $this->wallPostPermissions->mayDeleteFromWall($this->session->id(), $this->table, $this->id)
                    )
                ];
            }
        }
    }

    public function attachimage()
    {
        if (!$this->wallPostPermissions->mayWriteWall($this->session->id(), $this->table, $this->id)) {
            return XhrResponses::PERMISSION_DENIED;
        }

        if (isset($_FILES['etattach']['size']) && $_FILES['etattach']['size'] < 9_136_365 && $this->attach_allow($_FILES['etattach']['name'])) {
            $new_filename = uniqid('', true);

            $ext = strtolower((string)$_FILES['etattach']['name']);
            $ext = explode('.', $ext);
            if (count($ext) > 1) {
                $ext = end($ext);
                $ext = trim($ext);
                $ext = '.' . preg_replace('/[^a-z0-9]/', '', $ext);
            } else {
                $ext = '';
            }

            $new_filename .= $ext;

            move_uploaded_file($_FILES['etattach']['tmp_name'], 'images/wallpost/' . $new_filename);

            copy('images/wallpost/' . $new_filename, 'images/wallpost/thumb_' . $new_filename);
            copy('images/wallpost/' . $new_filename, 'images/wallpost/medium_' . $new_filename);
            $image = new fImage('images/wallpost/medium_' . $new_filename);
            $image->resize(530, 0);
            $image->saveChanges();

            $image = new fImage('images/wallpost/' . $new_filename);
            $image->resize(1000, 0);
            $image->saveChanges();

            $image = new fImage('images/wallpost/thumb_' . $new_filename);
            $image->cropToRatio(1, 1);
            $image->resize(75, 75);
            $image->saveChanges();

            $init = 'window.parent.finishImage("' . $new_filename . '");';
        } else {
            $init = 'window.parent.pulseInfo(\'' . $this->translator->trans('mailbox.filesize') . '\'); window.parent.resetUploader();';
        }

        echo '<html><head>

		<script type="text/javascript">
			function init () {
				' . $init . '
			}
		</script>

		</head><body onload="init();"></body></html>';

        exit;
    }

    public function attach_allow(string $filename): bool
    {
        if (strlen($filename) < 300) {
            $ext = explode('.', $filename);
            $ext = end($ext);
            $ext = strtolower($ext);
            $allowed = [
                'jpg' => true,
                'jpeg' => true,
                'png' => true,
                'gif' => true
            ];

            if (isset($allowed[$ext])) {
                return true;
            }
        }

        return false;
    }
}
