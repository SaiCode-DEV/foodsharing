<?php

namespace Foodsharing\Modules\Settings;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Map\MapConstants;
use Foodsharing\Modules\Core\DBConstants\Quiz\AnswerRating;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\IdentificationHelper;
use Foodsharing\Utility\ImageHelper;
use Foodsharing\Utility\NumberHelper;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Foodsharing\Utility\Sanitizer;
use Foodsharing\Utility\TimeHelper;
use Foodsharing\Utility\TranslationHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsView extends View
{
    private RegionGateway $regionGateway;

    public function __construct(
        \Twig\Environment $twig,
        Session $session,
        Utils $viewUtils,
        RegionGateway $regionGateway,
        DataHelper $dataHelper,
        IdentificationHelper $identificationHelper,
        ImageHelper $imageService,
        NumberHelper $numberHelper,
        PageHelper $pageHelper,
        RouteHelper $routeHelper,
        Sanitizer $sanitizerService,
        TimeHelper $timeHelper,
        TranslationHelper $translationHelper,
        TranslatorInterface $translator
    ) {
        $this->regionGateway = $regionGateway;

        parent::__construct(
            $twig,
            $session,
            $viewUtils,
            $dataHelper,
            $identificationHelper,
            $imageService,
            $numberHelper,
            $pageHelper,
            $routeHelper,
            $sanitizerService,
            $timeHelper,
            $translationHelper,
            $translator
        );
    }

    public function sleepMode($sleep): string
    {
        return $this->vueComponent('sleeping-mode', 'SleepingMode', [
            'sleepStatus' => $sleep['sleep_status'],
            'sleepFrom' => $sleep['sleep_from'],
            'sleepUntil' => $sleep['sleep_until'],
            'sleepMessage' => $sleep['sleep_msg']
        ]);
    }

    public function settingsInfo()
    {
        return $this->vueComponent('notifications', 'Notifications');
    }

    public function quizSession($session, $try_count, ContentGateway $contentGateway)
    {
        if ($session['fp'] <= $session['maxfp']) {
            $infotext = $this->v_utils->v_success($this->translator->trans('quiz.congrats_details', [
                '{points}' => $session['fp'],
                '{max_points}' => $session['maxfp']
            ]));
        } else {
            $infotext = $this->v_utils->v_error($this->translator->trans('quiz.not_passed_details', [
                '{points}' => $session['fp'],
                '{max_points}' => $session['maxfp']
            ]));
        }
        $this->pageHelper->addContent('<div class="quizsession">' . $this->topbar($session['name'] . $this->translator->trans('quiz.type'), '', '<img src="/img/quiz.png" />') . '</div>');
        $out = '';

        $out .= $infotext;

        if ($session['fp'] <= $session['maxfp']) {
            $btn = '';
            switch ($session['quiz_id']) {
                case 1:
                    $btn = '<a href="/?page=settings&sub=up_fs" class="button">' . $this->translator->trans('quiz.finishnow.fs') . '</a>';
                    break;

                case 2:
                    $btn = '<a href="/?page=settings&sub=up_bip" class="button">' . $this->translator->trans('quiz.finishnow.bv') . '</a>';
                    break;

                default:
                    break;
            }
            $out .= $this->v_utils->v_field('<p>' . $this->translator->trans('quiz.finished') . '</p><p>' . $this->translator->trans('quiz.resultsbelow') . '</p><p style="padding:15px;text-align:center;">' . $btn . '</p>', $this->translator->trans('quiz.heavysigh'), ['class' => 'ui-padding']);
        } else {
            /*
             * get the specific text from content table
             */
            $content_id = false;

            if ($try_count > 4) {
                $content_id = 13;
            } elseif ($try_count > 2) {
                $content_id = 21;
            } elseif ($try_count == 2) {
                $content_id = 20;
            } elseif ($try_count == 1) {
                $content_id = 19;
            }

            if ($content_id) {
                $cnt = $contentGateway->get($content_id);
                $out .= $this->v_utils->v_field($cnt['body'], $cnt['title'], ['class' => 'ui-padding']);
            }
        }

        $i = 0;
        foreach ($session['quiz_result'] as $r) {
            /*
             * If the question has no error points its a joke question lets store in clear in a variable
             */
            $was_a_joke = false;
            if ($r['fp'] == 0) {
                $was_a_joke = true;
            }

            /*
             * If the question has more than 10 error point its a k.o. question
            */
            $was_a_ko_question = false;
            if ($r['fp'] > 10) {
                $was_a_ko_question = true;
            }

            $ftext = $this->translator->trans('quiz.donecorrectly');
            ++$i;
            $cnt = '<div class="question">' . $r['text'] . '</div>';

            $cnt .= $this->v_utils->v_input_wrapper($this->translator->trans('quiz.wikilink'), '<a target="_blank" href="' . $r['wikilink'] . '">' . $r['wikilink'] . '</a>');

            $right_answers = '';
            $wrong_answers = '';
            $neutral_answers = '';
            $ai = 0;

            $sort_right = 'right';

            $noclicked = true;
            foreach ($r['answers'] as $a) {
                ++$ai;
                $right = 'red';

                if ($a['user_say']) {
                    $noclicked = false;
                }

                if (!$r['noco'] && $r['percent'] == 100) {
                    $atext = '';
                    $right = 'red';
                } elseif ($a['user_say'] == true && $a['right'] == AnswerRating::CORRECT && !$r['noco']) {
                    $right = 'green';
                    if ($a['right']) {
                        $atext = ' ' . $this->translator->trans('quiz.choice.correcta');
                        $sort_right = 'right';
                    } else {
                        $atext = ' ' . $this->translator->trans('quiz.choice.correctb');
                        $sort_right = 'right';
                    }
                } elseif ($a['right'] == AnswerRating::NEUTRAL) {
                    $atext = ' ' . $this->translator->trans('quiz.choice.neut');
                    $right = 'neutral';
                    $sort_right = 'neutral';
                } else {
                    if ($a['right']) {
                        $atext = ' ' . $this->translator->trans('quiz.choice.wronga');
                        $sort_right = 'false';
                    } else {
                        $atext = ' ' . $this->translator->trans('quiz.choice.wrongb');
                        $sort_right = 'false';
                    }
                }

                if ($sort_right == 'right') {
                    $right_answers .= '
					<div class="answer q-' . $right . '">
						' . $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.answer') . ' ' . $ai . $atext, $a['text']) . '
						' . $this->v_utils->v_input_wrapper($this->translator->trans('explanation'), $a['explanation']) . '

					</div>';
                } elseif ($sort_right == 'neutral') {
                    $neutral_answers .= '
					<div class="answer q-' . $right . '">
						' . $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.answer') . ' ' . $ai . $atext, $a['text']) . '
						' . $this->v_utils->v_input_wrapper($this->translator->trans('explanation'), $a['explanation']) . '

					</div>';
                } else {
                    $wrong_answers .= '
					<div class="answer q-' . $right . '">
						' . $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.answer') . ' ' . $ai . $atext, $a['text']) . '
						' . $this->v_utils->v_input_wrapper($this->translator->trans('explanation'), $a['explanation']) . '

					</div>';
                }
            }

            $no_wrong_right_sort = false;

            if ($r['userfp'] > 0) {
                $cnt .= $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.fpsum'), $r['userfp']);
                if ($r['percent'] == 100) {
                    $ftext = ' ' . $this->translator->trans('quiz.choice.sadlywrong');
                    if (!$r['noco'] && $noclicked) {
                        $no_wrong_right_sort = true;
                        $ftext = ' ' . $this->translator->trans('quiz.choice.alsowrong');
                    }
                } else {
                    $ftext = ' ' . $this->translator->trans('quiz.choice.percenta') . ' ' . (100 - $r['percent']) . ' ' . $this->translator->trans('quiz.choice.percentb');
                }
            }

            if ($no_wrong_right_sort) {
                $cnt .= $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.responses'), $wrong_answers . $right_answers, false, ['collapse' => true]);
            } else {
                if (!empty($right_answers)) {
                    $cnt .= $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.cresponses'), $right_answers, false, ['collapse' => true]);
                }
                if (!empty($wrong_answers)) {
                    $cnt .= $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.wresponses'), $wrong_answers, false, ['collapse' => true]);
                }
                if (!empty($neutral_answers)) {
                    $cnt .= $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.nresponses'), $neutral_answers, false, ['collapse' => true]);
                }
            }

            $cnt .= '<div id="qcomment-' . (int)$r['id'] . '">' . $this->v_utils->v_input_wrapper($this->translator->trans('quiz.choice.writecomment'), '<textarea style="height:50px;" id="comment-' . $r['id'] . '" name="desc" class="input textarea value"></textarea><br /><a class="button" href="#" onclick="ajreq(\'addcomment\',{app:\'quiz\',comment:$(\'#comment-' . (int)$r['id'] . '\').val(),id:' . (int)$r['id'] . '});return false;">' . $this->translator->trans('quiz.choice.send') . '</a>', false, ['collapse' => true]) . '</div>';

            /*
             * If the question was a joke question lets display it to the user!
             */
            if ($was_a_joke) {
                $ftext = $this->translator->trans('quiz.choice.joke') . ' <i class="far fa-smile"></i>';
            }

            /*
             * If the question is k.o. question and the user has error display a message to the user
             */
            if ($was_a_ko_question && $r['userfp'] > 0) {
                $ftext = $this->translator->trans('quiz.choice.koquestion');
                $cnt = $this->v_utils->v_info($this->translator->trans('quiz.choice.whatisko'));
            }

            $out .= '
					<div class="quizsession">' .
                $this->v_utils->v_field($cnt, $this->translator->trans('quiz.question') . ' ' . $i . ' ' . $ftext, ['class' => 'ui-padding']) . '
					</div>';
        }

        return $out;
    }

    public function changemail3($email)
    {
        return $this->v_utils->v_info($this->translator->trans('settings.changemail.question') . ' <strong>' . $email . '</strong> ?');
    }

    public function passport(): string
    {
        return $this->vueComponent('passport', 'Passport', [
            'userId' => $this->session->id(),
        ]);
    }

    public function settingsCalendar(): string
    {
        return $this->vueComponent('calendar', 'Calendar', [
            'baseUrlWebcal' => WEBCAL_URL . '/api/calendar/',
            'baseUrlHttp' => BASE_URL . '/api/calendar/'
        ]);
    }

    public function delete_account(int $fsId): string
    {
        return $this->vueComponent('delete-account', 'DeleteAccount', [
            'userId' => $fsId
        ]);
    }

    public function foodsaver_form()
    {
        global $g_data;

        $regionPicker = '';
        $position = '';

        if ($this->session->mayRole(Role::ORGA)) {
            $bezirk = ['id' => 0, 'name' => false];
            if ($b = $this->regionGateway->getRegion($this->session->getCurrentRegionId())) {
                $bezirk['id'] = $b['id'];
                $bezirk['name'] = $b['name'];
            }

            $regionPicker .= $this->vueComponent('region-tree-vform', 'RegionTreeVForm', [
                'title' => $this->translator->trans('terminology.homeRegion'),
                'inputName' => 'bezirk_id',
                'value' => $bezirk,
                'selectableRegionTypes' => [UnitType::CITY, UnitType::DISTRICT, UnitType::REGION, UnitType::WORKING_GROUP, UnitType::PART_OF_TOWN],
            ]);
            $position = $this->v_utils->v_form_text('position');
        }

        $g_data['ort'] = $g_data['stadt'];

        $addressPicker = $this->vueComponent('settings-address-search', 'LeafletLocationSearchVForm', [
            'zoom' => 17,
            'coordinates' => ['lat' => $g_data['lat'] ?? MapConstants::CENTER_GERMANY_LAT, 'lon' => $g_data['lon'] ?? MapConstants::CENTER_GERMANY_LON],
            'street' => $g_data['anschrift'],
            'postalCode' => $g_data['plz'],
            'city' => $g_data['ort'],
            'additionalInfoText' => $this->translator->trans('addresspicker.infobox_profile'),
        ]);

        return $this->v_utils->v_quickform($this->translator->trans('settings.header'), [
            $this->vueComponent('name-input', 'NameInput', [
                'name' => $this->dataHelper->getValue('name'),
                'lastName' => $this->dataHelper->getValue('nachname'),
                'regionId' => $this->dataHelper->getValue('bezirk_id'),
            ]),
            $this->v_utils->v_form_date('geb_datum', ['required' => true, 'yearRangeFrom' => (int)date('Y') - 120, 'yearRangeTo' => (int)date('Y') - 8]),
            $this->v_utils->v_form_text('handy', ['placeholder' => $this->translator->trans('register.phone_example')]),
            $this->v_utils->v_form_text('telefon', ['placeholder' => $this->translator->trans('register.landline_example')]),
            $regionPicker,
            $addressPicker,
            $position,
            $this->v_utils->v_form_textarea('about_me_intern', [
                'desc' => $this->translator->trans('foodsaver.about_me_intern'),
            ]),
            $this->v_utils->v_form_textarea('about_me_public', [
                'desc' => $this->translator->trans('foodsaver.about_me_public'),
            ]),
            $this->v_utils->v_form_text('homepage'),
        ], ['submit' => $this->translator->trans('button.save')]);
    }

    public function quizFailed($failed)
    {
        $out = $this->v_utils->v_field($failed['body'], $failed['title'], ['class' => 'ui-padding']);

        return $out;
    }

    public function pause($days_to_wait)
    {
        $out = $this->v_utils->v_input_wrapper($this->translator->trans('quiz.threestrikes'), $this->translator->trans('quiz.waitxdays') . $days_to_wait . $this->translator->trans('quiz.days') . '.');

        $out = $this->v_utils->v_field($out, $this->translator->trans('quiz.learnbreak'), ['class' => 'ui-padding']);

        return $out;
    }

    public function quizContinue($quiz)
    {
        $out = '';

        $out .= $this->v_utils->v_input_wrapper($this->translator->trans('quiz.notfinishedyet'), $this->translator->trans('quiz.safeandsound'));

        $out .= $quiz['desc'];

        $out .= '<p><a onclick="ajreq(\'startquiz\',{app:\'quiz\',qid:' . (int)$quiz['id'] . '});" href="#" class="button button-big">' . $this->translator->trans('quiz.continuenow') . '</a></p>';

        $out = $this->v_utils->v_field($out, $quiz['name'] . $this->translator->trans('quiz.continuetype'), ['class' => 'ui-padding']);

        return $out;
    }

    public function quizRetry($quiz, $failed_count, $max_failed_count)
    {
        $out = $this->v_utils->v_input_wrapper($this->translator->trans('quiz.trynumber') . ' ' . ($failed_count + 1), '<p>' . $failed_count . $this->translator->trans('quiz.failedbeforebut') . ' ' . ($max_failed_count - $failed_count) . '</p><p>' . $this->translator->trans('quiz.failedbeforebut') . '</p>');

        $out .= $quiz['desc'];

        $out .= '<p><a onclick="ajreq(\'startquiz\',{app:\'quiz\',qid:' . (int)$quiz['id'] . '});" href="#" class="button button-big">' . $this->translator->trans('quiz.timedstart') . '</a></p>';

        if ($quiz['id'] == 1) {
            $out .= '<p><a onclick="ajreq(\'startquiz\',{app:\'quiz\',easymode:1,qid:' . (int)$quiz['id'] . '});" href="#" class="button button-big">' . $this->translator->trans('quiz.regstart') . '</a></p>';
        }

        $out = $this->v_utils->v_field($out, $quiz['name'] . $this->translator->trans('quiz.quizleft'), ['class' => 'ui-padding']);

        return $out;
    }

    public function confirmBip($cnt, $rv)
    {
        $out = '
			<form action="/?page=settings&amp;sub=up_bip" enctype="multipart/form-data" class="validate" id="confirmfs-form" method="post">
				<input type="hidden" value="confirmfs" name="form_submit">';

        if ($cnt) {
            $out .= $this->v_utils->v_field($cnt['body'], $cnt['title'], ['class' => 'ui-padding']);
        }
        if ($rv) {
            $rv['body'] .= '
			<label><input id="rv-accept" class="input" type="checkbox" name="accepted" value="1">&nbsp;' . $this->translator->trans('foodsaver.upgrade.rv') . '</label>
			<div class="input-wrapper">
				<p><input type="submit" value="Bestätigen" class="button"></p>
			</div>';

            $out .= $this->v_utils->v_field($rv['body'], $rv['title'], ['class' => 'ui-padding']);
        }

        $out .= '
			</form>';

        return $out;
    }

    public function confirmFs($cnt, $rv)
    {
        $out = '
			<form action="/?page=settings&amp;sub=up_fs" enctype="multipart/form-data" class="validate" id="confirmfs-form" method="post">
				<input type="hidden" value="confirmfs" name="form_submit">';

        if ($cnt) {
            $out .= $this->v_utils->v_field($cnt['body'], $cnt['title'], ['class' => 'ui-padding']);
        }
        if ($rv) {
            $rv['body'] .= '
			<label><input id="rv-accept" class="input" type="checkbox" name="accepted" value="1">&nbsp;' . $this->translator->trans('foodsaver.upgrade.rv') . '</label>
			<div class="input-wrapper">
				<p><input type="submit" value="Bestätigen" class="button"></p>
			</div>';

            $out .= $this->v_utils->v_field($rv['body'], $rv['title'], ['class' => 'ui-padding']);
        }

        $out .= '
			</form>';

        return $out;
    }

    public function quizIndex($quiz)
    {
        $out = '';

        $out .= nl2br($quiz['desc']);

        if ($quiz['id'] == 1) {
            $out .= '<p><a onclick="ajreq(\'startquiz\',{app:\'quiz\',qid:' . (int)$quiz['id'] . '});" href="#" class="button button-big">' . $this->translator->trans('quiz.timedstart') . '</a></p>';
            $out .= '<p><a onclick="ajreq(\'startquiz\',{app:\'quiz\',easymode:1,qid:' . (int)$quiz['id'] . '});" href="#" class="button button-big">' . $this->translator->trans('quiz.regstart') . '</a></p>';
        } else {
            $out .= '<p><a onclick="ajreq(\'startquiz\',{app:\'quiz\',qid:' . (int)$quiz['id'] . '});" href="#" class="button button-big">' . $this->translator->trans('quiz.nownownow') . '</a></p>';
        }

        $out = $this->v_utils->v_field($out, $quiz['name'] . $this->translator->trans('quiz.quizleft'), ['class' => 'ui-padding']);

        return $out;
    }

    public function picture_box($photo): string
    {
        $p_cnt = $this->v_utils->v_info($this->translator->trans('settings.photo.info', [
            '{link_photo}' => 'https://wiki.foodsharing.de/Foto_-_Leitfaden_f%C3%BCr_ein_repr%C3%A4sentatives_Foto',
            '{link_id}' => 'https://wiki.foodsharing.de/Ausweis',
            '{link_fs}' => 'https://wiki.foodsharing.de/Foodsaver',
        ]));

        // find previous picture
        $initialValue = 'img/portrait.png';
        if (!empty($photo)) {
            if (strpos($photo, '/api/uploads/') === 0) {
                // path for pictures uploaded with the new API
                $initialValue = $photo . '?w=200&h=257';
            } elseif (file_exists('images/thumb_crop_' . $photo)) {
                // backward compatible path for old pictures
                $initialValue = 'images/thumb_crop_' . $photo;
            }
        }

        // create picture upload component
        $p_cnt .= $this->vueComponent('image-upload', 'profile-picture', [
            'initialValue' => $initialValue,
            'imgHeight' => 400,
            'imgWidth' => 400,
        ]);

        return $this->v_utils->v_field($p_cnt, $this->translator->trans('settings.photo.title'));
    }
}
