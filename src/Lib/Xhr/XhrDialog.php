<?php

namespace Foodsharing\Lib\Xhr;

use Foodsharing\Utility\Sanitizer;
use Symfony\Contracts\Translation\TranslatorInterface;

class XhrDialog
{
    private $id;
    private $buttons = [];
    private $content = '';
    private $options = [];
    private $script = '';
    private $scriptBefore = '';
    private $scriptAfter;
    private $classnames = [];
    private readonly TranslatorInterface $translator;
    private readonly Sanitizer $sanitizerService;

    public function __construct($title = false)
    {
        global $container;
        $this->translator = $container->get('translator'); // TODO TranslatorInterface is an alias
        $this->id = 'd-' . uniqid();
        $this->sanitizerService = $container->get(Sanitizer::class);

        if ($title !== false) {
            $this->setTitle($title);
        }

        $this->addOpt('modal', 'true', false);
        $this->addJs('
			$("#' . $this->id . '").dialog({
			    position: { "my": "center", "at": "center" }
			});
		');
    }

    public function addClass($name)
    {
        $this->classnames[] = $name;
    }

    public function addOpt($opt, $value, $quotes = true)
    {
        if ($quotes) {
            $quotes = '"';
        } else {
            $quotes = '';
        }
        $this->options[$opt] = $quotes . $value . $quotes;
    }

    public function setTitle($title)
    {
        $this->addOpt('title', $this->sanitizerService->jsSafe($title, '"'));
    }

    public function addJsBefore($js)
    {
        $this->scriptBefore .= $js;
    }

    public function addJsAfter($js)
    {
        $this->scriptAfter .= $js;
    }

    public function addJs($js)
    {
        $this->script .= $js;
    }

    public function addContent($html)
    {
        $this->content .= $html;
    }

    public function getId()
    {
        return $this->id;
    }

    public function addAbortButton()
    {
        $this->buttons[] = [
            'text' => $this->translator->trans('button.cancel'),
            'click' => '$("#' . $this->id . '").dialog("close");'
        ];
    }

    public function addButton($text, $click)
    {
        $this->buttons[] = [
            'text' => $text,
            'click' => $click
        ];
    }

    public function setResizeable($val = true)
    {
        $val = $val ? 'true' : 'false';
        $this->addOpt('resizable', $val, false);
    }

    public function noOverflow()
    {
        $this->addOpt('maxHeight', '$(window).height()-30', false);
        $this->addJsAfter('
			if ($("#' . $this->getId() . '").width() > $(window).width()) {
				$("#' . $this->getId() . '").dialog("option", "width", $(window).width() - 30);
			}

			$(window).on("resize", function () {
				$("#' . $this->getId() . '").dialog("option", "maxHeight", $(window).height() - 30);
			});');
    }

    public function xhrout(): array
    {
        $buttons = [];
        foreach ($this->buttons as $b) {
            $buttons[] = '{"text":\'' . $b['text'] . '\', click: function () {' . $b['click'] . '}}';
        }

        $this->addOpt('buttons', '[' . implode(',', $buttons) . ']', false);

        $this->addJs('$("#' . $this->id . '").dialog("option", "position", "center");');

        $options = [];
        foreach ($this->options as $opt => $value) {
            $options[] = $opt . ':' . $value;
        }

        $classjs = '';
        if (!empty($this->classnames)) {
            $classjs = '$("#' . $this->id . '").parent().addClass("' . implode(' ', $this->classnames) . '")';
        }

        return [
            'status' => 1,
            'script' => $this->scriptBefore . '
				if ($(".xhrDialog").length > 0) {
					$(".xhrDialog").dialog("close");
					$(".xhrDialog").dialog("destroy");
					$(".xhrDialog").remove();
				}
				$("body").append(\'<div class="xhrDialog" style="display: none;" id="' . $this->id . '"></div>\');
				$("#' . $this->id . '").html(\'' . $this->sanitizerService->jsSafe($this->content) . '\');
				$(".xhrDialog .input.textarea").css("height", "50px");
				$("#' . $this->id . '").dialog({
					' . implode(',', $options) . '
				});'
                . $this->script . $this->scriptAfter . $classjs
        ];
    }
}
