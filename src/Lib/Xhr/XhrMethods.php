<?php

namespace Foodsharing\Lib\Xhr;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Email\EmailStatus;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Email\EmailGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Permissions\NewsletterEmailPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\Sanitizer;
use Symfony\Contracts\Translation\TranslatorInterface;

class XhrMethods
{
    public function __construct(
        private readonly Session $session,
        private readonly Database $database,
        private readonly Utils $v_utils,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly GroupGateway $groupGateway,
        private readonly RegionGateway $regionGateway,
        private readonly StoreGateway $storeGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly EmailGateway $emailGateway,
        private readonly MailboxGateway $mailboxGateway,
        private readonly Sanitizer $sanitizerService,
        private readonly EmailHelper $emailHelper,
        private readonly NewsletterEmailPermissions $newsletterEmailPermissions,
        private readonly RegionPermissions $regionPermissions,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function xhr_continueMail($data)
    {
        if ($this->newsletterEmailPermissions->mayAdministrateNewsletterEmail()) {
            $mail_id = (int)$data['id'];

            $mail = $this->emailGateway->getOne_send_email($mail_id);
            $recip = $this->emailGateway->getMailNext($mail_id);

            if (empty($recip)) {
                return json_encode([
                    'status' => 2,
                    'comment' => $this->translator->trans('recipients.done'),
                ]);
            }

            $mailbox = $this->mailboxGateway->getMailbox((int)$mail['mailbox_id']);
            $mailbox['email'] = $mailbox['name'] . '@' . PLATFORM_MAILBOX_HOST;

            $sender = $this->database->fetchByCriteria('foodsaver', ['geschlecht', 'name'], ['id' => $this->session->id()]);

            $this->emailGateway->setEmailStatus($mail['id'], $recip, EmailStatus::STATUS_INITIALISED);

            foreach ($recip as $fs) {
                $anrede = $this->translator->trans('salutation.' . $fs['geschlecht']);

                $search = ['{NAME}', '{ANREDE}', '{EMAIL}'];
                $replace = [$fs['name'], $anrede, $fs['email']];

                $attach = false;
                if (!empty($mail['attach'])) {
                    $attach = json_decode($mail['attach'], true);
                }

                $message = str_replace($search, $replace, $mail['message']);
                $subject = str_replace($search, $replace, $mail['name']);

                $check = false;
                if ($this->emailHelper->libmail($mailbox, $fs['email'], $subject, $message, $attach, $fs['token'])) {
                    $check = true;
                }

                if (!$check) {
                    $this->emailGateway->setEmailStatus($mail['id'], [$fs['id']], EmailStatus::STATUS_INVALID_MAIL);
                } else {
                    $this->emailGateway->setEmailStatus($mail['id'], [$fs['id']], EmailStatus::STATUS_SENT);
                }
            }

            $mails_left = $this->emailGateway->getMailsLeft($mail['id']);
            if ($mails_left) {
                // throttle to 5 mails per second here to avoid queue bloat
                sleep(2);
            }
            $current = $fs['email'] ?? $this->translator->trans('recipients.unknown');

            return json_encode([
                'left' => $mails_left,
                'status' => 1,
                'comment' => $this->translator->trans('recipients.status', ['{current}' => $current]),
            ]);
        }

        return 0;
    }

    public function xhr_newregion($data)
    {
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            return;
        }

        $data['name'] = strip_tags($data['name']);
        $data['name'] = str_replace(['/', '"', "'", '.', ';'], '', $data['name']);
        $data['has_children'] = 0;
        $data['email_pass'] = '';
        $data['email_name'] = 'foodsharing ' . $data['name'];

        if (empty($data['name'])) {
            return;
        }

        $out = $this->regionGateway->addRegion($data);

        if (!$out) {
            return;
        }

        $parentId = intval($data['parent_id']);
        $this->database->update('fs_bezirk', ['has_children' => 1], ['id' => $parentId]);

        return json_encode([
            'status' => 1,
            'script' => '$("#tree").dynatree("getTree").reload(); pulseInfo("'
                . $this->translator->trans('region.created', ['{region}' => $data['name']]) .
                '");',
        ]);
    }

    public function xhr_bezirkTree($data)
    {
        $region = $this->regionGateway->getBezirkByParent(
            $data['p'],
            $this->regionPermissions->mayAdministrateRegions()
                || $this->newsletterEmailPermissions->mayAdministrateNewsletterEmail()
        );
        if (!$region) {
            $out = ['status' => 0];
        } else {
            $out = [];
            foreach ($region as $r) {
                $hasChildren = false;
                if ($r['has_children'] == 1) {
                    $hasChildren = true;
                }
                $out[] = [
                    'title' => $r['name'],
                    'isLazy' => $hasChildren,
                    'isFolder' => $hasChildren,
                    'ident' => $r['id'],
                    'type' => $r['type'],
                ];
            }
        }

        return json_encode($out);
    }

    public function xhr_getBezirk($data)
    {
        global $g_data;

        if (!$this->regionPermissions->mayAdministrateRegions()) {
            return XhrResponses::PERMISSION_DENIED;
        }
        $g_data = $this->groupGateway->getGroupLegacy($data['id']);

        $g_data['mailbox_name'] = '';
        if ($mbname = $this->mailboxGateway->getMailboxname($g_data['mailbox_id'])) {
            $g_data['mailbox_name'] = $mbname;
        }

        $out = [];
        $out['status'] = 1;

        $inputs = '<input type="text" name="botschafter[]" value="" class="tag input text value" />';
        if (!empty($g_data['foodsaver'])) {
            $inputs = '';
            if (isset($g_data['botschafter']) && is_array($g_data['botschafter'])) {
                foreach ($g_data['botschafter'] as $fs) {
                    $inputs .= '<input type="text" name="botschafter[' . $fs['id'] . '-a]" value="' . $fs['name'] . '" class="tag input text value" />';
                }
            }
        }

        $inputs = '<div id="botschafter">' . $inputs . '</div>';

        $regions = $this->regionGateway->getBasics_bezirk();
        $out['html'] = $this->v_utils->v_form('bezirkForm', [
            $this->v_utils->v_form_hidden('bezirk_id', (int)$data['id']),
            $this->v_utils->v_form_select('parent_id', ['values' => $regions]),
            $this->v_utils->v_form_select('master', [
                'label' => $this->translator->trans('region.hull.parent'),
                'desc' => $this->translator->trans('region.hull.parent-info'),
                'values' => $regions,
            ]),
            $this->v_utils->v_form_text('name'),
            $this->v_utils->v_form_text('mailbox_name', [
                'desc' => $this->translator->trans('region.mail.name-info'),
            ]),
            $this->v_utils->v_form_text('email_name', [
                'label' => $this->translator->trans('region.mail.sender'),
            ]),
            $this->v_utils->v_form_select('type', [
                'label' => $this->translator->trans('region.type.title'),
                'values' => [
                    ['id' => UnitType::CITY, 'name' => $this->translator->trans('region.type.city')],
                    ['id' => UnitType::BIG_CITY, 'name' => $this->translator->trans('region.type.bigcity')],
                    ['id' => UnitType::PART_OF_TOWN, 'name' => $this->translator->trans('region.type.townpart')],
                    ['id' => UnitType::DISTRICT, 'name' => $this->translator->trans('region.type.district')],
                    ['id' => UnitType::REGION, 'name' => $this->translator->trans('region.type.region')],
                    ['id' => UnitType::FEDERAL_STATE, 'name' => $this->translator->trans('region.type.state')],
                    ['id' => UnitType::COUNTRY, 'name' => $this->translator->trans('region.type.country')],
                    ['id' => UnitType::WORKING_GROUP, 'name' => $this->translator->trans('region.type.workgroup')],
                ],
            ]),
            $this->v_utils->v_form_select('workgroup_function', [
                'label' => $this->translator->trans('group.function.title'),
                'values' => [
                    ['id' => WorkgroupFunction::WELCOME, 'name' => $this->translator->trans('group.function.welcome')],
                    ['id' => WorkgroupFunction::VOTING, 'name' => $this->translator->trans('group.function.voting')],
                    ['id' => WorkgroupFunction::FSP, 'name' => $this->translator->trans('group.function.fsp')],
                    ['id' => WorkgroupFunction::STORES_COORDINATION, 'name' => $this->translator->trans('group.function.stores')],
                    ['id' => WorkgroupFunction::REPORT, 'name' => $this->translator->trans('group.function.report')],
                    ['id' => WorkgroupFunction::MEDIATION, 'name' => $this->translator->trans('group.function.mediation')],
                    ['id' => WorkgroupFunction::ARBITRATION, 'name' => $this->translator->trans('group.function.arbitration')],
                    ['id' => WorkgroupFunction::FSMANAGEMENT, 'name' => $this->translator->trans('group.function.fsmanagement')],
                    ['id' => WorkgroupFunction::PR, 'name' => $this->translator->trans('group.function.pr')],
                    ['id' => WorkgroupFunction::MODERATION, 'name' => $this->translator->trans('group.function.moderation')],
                    ['id' => WorkgroupFunction::BOARD, 'name' => $this->translator->trans('group.function.board')],
                    ['id' => WorkgroupFunction::ELECTION, 'name' => $this->translator->trans('group.function.election')],
                ],
            ]),
            $this->v_utils->v_input_wrapper(
                $this->translator->trans('terminology.ambassadors'),
                $inputs,
                'botschafter'
            )
        ], ['submit' => $this->translator->trans('button.save')])
            .
            $this->v_utils->v_input_wrapper(
                $this->translator->trans('region.hull.title'),
                '<a class="button" href="#" onclick="'
                    . 'if (confirm(\'' . $this->translator->trans('region.hull.confirm') . '\')) {'
                    . 'tryMasterUpdate(' . (int)$data['id'] . ');} return false;'
                    . '">'
                    . $this->translator->trans('region.hull.start')
                    . '</a>',
                'masterupdate',
                [
                    'desc' => $this->translator->trans('region.hull.closure', [
                        '{region}' => $g_data['name'],
                    ]),
                ]
            );

        $out['script'] = '
		$("#bezirkform-form").off("submit");
		$("#bezirkform-form").on("submit", function (ev) {
			ev.preventDefault();

			$("#dialog-confirm-msg").html("' . $this->translator->trans('region.confirm') . '");

			$("#dialog-confirm").dialog("option", "buttons", {
				"' . $this->translator->trans('region.save') . '": function () {
					showLoader();
					$.ajax({
						url: "/xhr?f=saveBezirk",
						data: $("#bezirkform-form").serialize(),
						dataType: "json",
						success: function (data) {
							$("#info-msg").html("");
							$.globalEval(data.script);
							$("#dialog-confirm").dialog("close");
							$("#tree").dynatree("getTree").reload();
						},
						complete: function () {
							hideLoader();
						}
					});
				},
				"' . $this->translator->trans('region.cancel') . '": function () {
					$("#dialog-confirm").dialog("close");
				}
			});

			$("#dialog-confirm").dialog("open");
		});

		$("input[type=\'submit\']").button();

		$("#botschafter input").tagedit({
			autocompleteURL: async function (request, response) {
			  let data = null
			  try {
			    data = await searchUser(request.term)
			  } catch (e) {
			  }
			  response(data)
			},
			allowEdit: false,
			allowAdd: false
		});

		$(window).on("keydown", function (event) {
			if (event.keyCode == 13) {
				event.preventDefault();
				return false;
			}
		});';

        if ($foodsaver = $this->foodsaverGateway->getFsMap($data['id'])) {
            $out['foodsaver'] = $foodsaver;
        }

        if ($betriebe = $this->storeGateway->getMapsStores($data['id'])) {
            $out['betriebe'] = $betriebe;
            foreach ($out['betriebe'] as $i => $b) {
                $out['betriebe'][$i]['bubble'] = '<div style="height: 110px; overflow: hidden; width: 270px; ">'
                    . '<div style="margin-right: 5px; float: right;"></div>'
                    . '<h1 style="font-size: 13px; font-weight: bold; margin-bottom: 8px;">'
                    . '<a href="/?page=fsbetrieb&id=' . (int)$b['id'] . '">'
                    . $this->sanitizerService->jsSafe($b['name'])
                    . '</a>'
                    . '</h1>'
                    . '<p>' . $this->sanitizerService->jsSafe($b['str']) . '</p>'
                    . '<p>' . $this->sanitizerService->jsSafe($b['plz'] . ' ' . $b['stadt']) . '</p>'
                    . '</div><div class="clear"></div>';
            }
        }

        return json_encode($out);
    }

    public function xhr_saveBezirk($data)
    {
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            return;
        }

        global $g_data;
        $g_data = $data;
        $regionId = intval($data['bezirk_id']);
        $parentId = intval($data['parent_id']);

        if ($data['workgroup_function'] && !$this->regionPermissions->mayAdministrateWorkgroupFunction(intval($data['workgroup_function']))) {
            return json_encode([
                'status' => 1,
                'script' => 'pulseError("' . $this->translator->trans('group.function.restricted_workgroup_function') . '");',
            ]);
        }

        // Check for: Only a workgroup can have a function.
        // If the workgroup is set to welcome Team - make sure there can be only one Welcome Team in a region.
        if (!UnitType::isGroup($data['type']) && $data['workgroup_function']) {
            return json_encode([
                'status' => 1,
                'script' => 'pulseError("' . $this->translator->trans('group.function.invalid') . '");',
            ]);
        } elseif ($data['workgroup_function'] == WorkgroupFunction::WELCOME) {
            $welcomeGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($parentId, WorkgroupFunction::WELCOME);
            if ($welcomeGroupId && ($welcomeGroupId != $regionId)) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_welcome_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::VOTING) {
            $votingGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($parentId, WorkgroupFunction::VOTING);
            if ($votingGroupId !== null && $votingGroupId !== $regionId) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_voting_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::FSP) {
            $fspGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($parentId, WorkgroupFunction::FSP);
            if ($fspGroupId !== null && $fspGroupId !== $regionId) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_fsp_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::STORES_COORDINATION) {
            $storesGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($parentId, WorkgroupFunction::STORES_COORDINATION);
            if ($storesGroupId !== null && $storesGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_stores_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::REPORT) {
            $reportGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($data['parent_id'], WorkgroupFunction::REPORT);
            if ($reportGroupId !== null && $reportGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_report_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::MEDIATION) {
            $mediationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($data['parent_id'], WorkgroupFunction::MEDIATION);
            if ($mediationGroupId !== null && $mediationGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_mediation_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::ARBITRATION) {
            $arbitrationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($data['parent_id'], WorkgroupFunction::ARBITRATION);
            if ($arbitrationGroupId !== null && $arbitrationGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_arbitration_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::FSMANAGEMENT) {
            $fsmanagementGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($data['parent_id'], WorkgroupFunction::FSMANAGEMENT);
            if ($fsmanagementGroupId !== null && $fsmanagementGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_fsmanagement_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::PR) {
            $prGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($data['parent_id'], WorkgroupFunction::PR);
            if ($prGroupId !== null && $prGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_pr_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::MODERATION) {
            $moderationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($data['parent_id'], WorkgroupFunction::MODERATION);
            if ($moderationGroupId !== null && $moderationGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_moderation_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::BOARD) {
            $boardGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($data['parent_id'], WorkgroupFunction::BOARD);
            if ($boardGroupId !== null && $boardGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_board_team') . '");',
                ]);
            }
        } elseif ($data['workgroup_function'] == WorkgroupFunction::ELECTION) {
            $electionGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($data['parent_id'], WorkgroupFunction::ELECTION);
            if ($electionGroupId !== null && $electionGroupId !== (int)$data['bezirk_id']) {
                return json_encode([
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('group.function.duplicate_election_team') . '");',
                ]);
            }
        }

        $oldRegionData = $this->groupGateway->getGroupLegacy($regionId);

        if (strlen($g_data['mailbox_name']) > 1) {
            try {
                $mbid = (int)$this->database->fetchValue('SELECT mailbox_id FROM fs_bezirk WHERE id = ?', [$regionId]);
                $this->database->update('fs_mailbox', ['name' => strip_tags($g_data['mailbox_name'])], ['id' => $mbid]);
            } catch (DatabaseNoValueFoundException) {
                $mbid = $this->database->insert('fs_mailbox', ['name' => strip_tags($g_data['mailbox_name'])]);
                $this->database->update('fs_bezirk', ['mailbox_id' => $mbid], ['id' => $regionId]);
            }
        }

        $this->sanitizerService->handleTagSelect('botschafter');

        // If the workgroup is moved it loses the old functions.
        // else a region is moved, all workgroups loose their related targets
        if ($oldRegionData['parent_id'] != $parentId) {
            if (UnitType::isGroup($oldRegionData['type'])) {
                if ($oldRegionData['workgroup_function']) {
                    $this->groupFunctionGateway->deleteRegionFunction($regionId, $oldRegionData['workgroup_function']);
                }
            } else {
                $this->groupFunctionGateway->deleteTargetFunctions($regionId);
            }
            $oldRegionData = $this->groupGateway->getGroupLegacy($regionId);
        }

        $this->regionGateway->update_bezirkNew($regionId, $g_data);

        $functionId = $g_data['workgroup_function'];
        $oldFunctionId = $oldRegionData['workgroup_function'];
        if ($functionId && !$oldFunctionId) {
            if (WorkgroupFunction::isValidFunction($functionId)) {
                $this->groupFunctionGateway->addRegionFunction($regionId, $parentId, $functionId);
            }
        } elseif ($functionId != $oldFunctionId) {
            $this->groupFunctionGateway->deleteRegionFunction($regionId, $oldFunctionId);
        }

        return json_encode([
            'status' => 1,
            'script' => 'pulseInfo("' . $this->translator->trans('region.edit_success') . '");',
        ]);
    }

    public function xhr_abortEmail($data)
    {
        $mailOwnerId = $this->emailGateway->getOne_send_email($data['id'])['foodsaver_id'];
        if ($this->session->id() == $mailOwnerId) {
            $this->emailGateway->setEmailStatus($data['id'], $mailOwnerId, EmailStatus::STATUS_CANCELED);
        }
    }
}
