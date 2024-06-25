<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class QuizMigration extends AbstractMigration
{
    public function change(): void
    {
        $body = '<div class=\"ace-line\">\n        <p><span>Liebe*r&nbsp;{NAME},<br /><br /></span>sch&ouml;n, dass Du dabei bist und Dich gegen die Lebensmittelverschwendung einsetzen willst!<br />Willst Du in Zukunft wie schon tausende andere&nbsp;<strong>Foodsaver werden und Lebensmittel bei B&auml;ckereien, Superm&auml;rkten, Restaurants etc.&nbsp;retten?</strong><br />Vielleicht sogar BetriebsverantwortlicheR werden oder Dich bei einen der unz&auml;hligen Arbeitsgruppen einbringen? Solltest Du&nbsp;Lust auf noch mehr Verantwortung haben und Deine Region mitaufbauen wollen bzw. bestehende BotschafterInnen unterst&uuml;tzen wollen, kannst Du Dich auch als&nbsp;foodsharing BotschafterIn bewerben. Lese Dich jetzt in die notwendigen <a href=\"https://wiki.foodsharing.de/Foodsaver\" target=\"_blank\">Dokumente im Wiki</a>&nbsp;ein, um dann <a href=\"/?page=settings&amp;sub=rise_role&role=1\" target=\"_blank\">das kleine Quiz</a> zu absolvieren.<br /><br />Du hast die M&ouml;glichkeit zwischen dem Quiz mit 10 Fragen und Zeitlimit oder dem&nbsp;<span>Quiz mit 20 Fragen ohne Zeitlimit.<br /><br /></span><strong>Sch&ouml;n, dass Du dabei bist und Dich einbringen willst! Wir freuen uns auf Deine Unterst&uuml;tzung!</strong></p>\n        <span><span>Herzlich Dein&nbsp;foodsharing Orgateam</span></span></div>';
        $this->query("UPDATE
                `fs_content`
            SET
                `body` = '{$body}',
                `last_mod` = '2024-04-08 11:38:10'
            WHERE `id` = 33");
    }
}
