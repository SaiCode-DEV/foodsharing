<?php

namespace Foodsharing\Modules\Core;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\FlashMessageHelper;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class Control
{
    protected $view;
    private false|string $sub;

    protected PageHelper $pageHelper;
    protected Mem $mem;
    protected Session $session;
    protected Utils $v_utils;
    private \Twig\Environment $twig;
    protected Request $request;
    protected EmailHelper $emailHelper;
    protected FlashMessageHelper $flashMessageHelper;
    protected RouteHelper $routeHelper;
    protected TranslatorInterface $translator;

    public function __construct()
    {
        global $container;
        $this->mem = $container->get(Mem::class);
        $this->session = $container->get(Session::class);
        $this->v_utils = $container->get(Utils::class);
        $this->pageHelper = $container->get(PageHelper::class);
        $this->emailHelper = $container->get(EmailHelper::class);
        $this->routeHelper = $container->get(RouteHelper::class);
        $this->flashMessageHelper = $container->get(FlashMessageHelper::class);
        $this->translator = $container->get('translator'); // TODO TranslatorInterface is an alias

        $this->sub = false;
        if (isset($_GET['sub'])) {
            $sub = $_GET['sub'];

            if (method_exists($this, $sub)) {
                $this->sub = $sub;
            }
        }
    }

    #[Required]
    public function setTwig(\Twig\Environment $twig): void
    {
        $this->twig = $twig;
    }

    public function setRequest(Request $req): void
    {
        $this->request = $req;
    }

    protected function render(string $template, array $data = []): string
    {
        $global = $this->pageHelper->generateAndGetGlobalViewData();
        $viewData = array_merge($global, $data);

        return $this->twig->render($template, $viewData);
    }

    public function getSub()
    {
        return $this->sub;
    }

    public function wallposts($table, $id): string
    {
        $posthtml = '';
        if ($this->session->mayRole()) {
            $posthtml = '
				<div class="tools ui-padding">
				<textarea id="wallpost-text" name="text" class="comment textarea"></textarea>
				<div id="attach-preview"></div>
				<div style="display: none;" id="wallpost-attach" /></div>

				<div id="wallpost-submit" align="right">

					<span id="wallpost-loader"></span><span id="wallpost-attach-image"><i class="far fa-image"></i> ' . $this->translator->trans('button.attach_image') . '</span>
					<a href="#" id="wall-submit">' . $this->translator->trans('button.send') . '</a>
					<div style="overflow: hidden; height: 0;">
						<form id="wallpost-attachimage-form" action="/xhrapp?app=wallpost&m=attachimage&table=' . $table . '&id=' . $id . '" method="post" enctype="multipart/form-data" target="wallpost-frame">
							<input id="wallpost-attach-trigger" type="file" accept="image/png, image/jpeg" maxlength="100000" size="chars" name="etattach" />
						</form>
					</div>

				</div>
				<div class="clear"></div>
				<div style="visibility: hidden;">
				<iframe name="wallpost-frame" style="height: 1px;" frameborder="0"></iframe>
				</div>
			</div>';
        }

        return '
		<div id="wallposts">
			' . $posthtml . '
			<div class="wall-posts">

			</div>
		</div>';
    }

    public function submitted(): bool
    {
        return !empty($_POST);
    }
}
