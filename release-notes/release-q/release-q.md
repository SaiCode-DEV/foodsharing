Es gibt Neuigkeiten aus der foodsharing-Entwicklung! Das neueste Update, Release Quiche, ist da! 🥧

In den letzten Monaten hat sich einiges getan, und wir haben wieder eine Liste mit den wichtigsten Änderungen für euch zusammengestellt.

Wie immer gab es neben den größeren Veränderungen auch zahlreiche kleinere Verbesserungen, Fehlerbehebungen und technische Optimierungen. Diese führen wir hier nicht alle auf – wer ins Detail gehen möchte, findet im GitLab die komplette Übersicht mit allen Änderungen und den beteiligten Entwickler:innen.

Unser Ziel bleibt es, foodsharing kontinuierlich zu verbessern, damit wir uns noch effizienter gegen Lebensmittelverschwendung organisieren können. Falls euch etwas auffällt oder ihr Feedback habt, schreibt es gerne in diesen Thread: Feedback zum Release Quiche. Und wenn ihr Ideen für zukünftige Updates habt, bringt sie gerne in der überregionalen AG Produktteam ein.

## Spendenseite
Die Spendenseite wurde grundlegend erneuert (!4633, !4836).

---



## Betriebe
- Im Betriebsprotokoll werden jetzt auch das Ändern der Slot-Anzahl und der Slot-Beschreibung, das Löschen von Slots und das Ein- und Ausschalten der Bezirks-Abholregel festgehalten. (!5258)

## Betriebsketten
- Für Betriebsketten kann jetzt auch der Status "Existiert nicht mehr" und "Zu wenige Filialen" eingestellt werden. (!4534)

## Chat
- Beim Öffnen eines Chats auf Touch-Geräten klappt die Bildschirmtastatur nicht mehr automatisch auf. (!5184)
- Beim Öffnen der Tastatur in einem Chat auf dem Smartphone-Bildschirm wird der Chat-Header nicht nach oben geschoben. (!5208)
- Im Chat-Popup konnte die Antwort-Vorschau bei langen zitierten Nachrichten das ganze Fenster ausfüllen - Scrollen, Eingabefeld und Optionen waren dann nicht mehr erreichbar. Lange Zitate werden jetzt eingeklappt und scrollen in sich. (!5238)

## Einstellungen
- Orgas können die Zeitzone eines Bezirks jetzt in der Bezirksverwaltung einsehen und einstellen. (!5289)


## Inhalte
- Neue öffentliche Seite für den "Tag der Lebensmittelrettung 2026" unter /tdl-2026, editierbar durch die Admins der TdL-Arbeitsgruppe. (!5095)
- Die Unterseite "Vergangene Kampagnen" wurde auf Wunsch der Politischen Kampagnen entfernt. (!5294)
- Tabellen in Markdown-Texten werden jetzt überall im Web als Tabelle dargestellt (z.B. im Forum, an der Pinnwand und in Betriebsbeschreibungen) statt als Rohtext mit Strichen. (!5178)

## Kalender
- Die Besonderheiten eines Betriebs werden jetzt auch im synchronisierten Abholtermin im Kalender angezeigt. (!5158)
- Der Kalender-Export zum Abonnieren funktioniert wieder zuverlässig, auch wenn ein Termin sehr weit in der Zukunft liegt. Vorher konnte ein einzelner solcher Termin den gesamten Kalender lahmlegen. (!5165)

## Nachrichten
- In Gruppenchats steht jetzt die Anzahl der Teilnehmenden hinter dem Namen (z.B. "Team XY (15 Teilnehmer:innen)"), damit klarer ist, an wie viele Personen eine Nachricht geht. (!5166)
- Benachrichtigungen in sehr großen Gruppenchats kommen wieder zuverlässig an. Bisher scheiterte die Zustellung an der Länge der internen Anfrage. (!5283)
- Die Anzahl ungelesener E-Mails aktualisiert sich nach dem Lesen, Verschieben oder Löschen jetzt ohne Neuladen und stimmt auch in mehreren geöffneten Tabs. (!5318)
- In E-Mails aus einem Postfach bleiben spitze Klammern erhalten. Bisher fehlte der Text dahinter, sobald zum Beispiel eine Mailadresse in spitzen Klammern im Text stand. (!5347)
- Wird "Nachricht an Springer" geöffnet, ohne etwas zu schreiben, bleibt kein leerer Chat mehr in der Nachrichtenliste zurück. (!5357)
- Die geöffnete Unterhaltung steht jetzt in der Adresse der Nachrichtenseite. Nach dem Neuladen ist sie weiterhin offen und lässt sich verlinken. (!5360)
- Steht eine Adresse in einer Postfach-Mail in eckigen Klammern, führt der Link jetzt zum Ziel statt ins Leere. (!5368)
- Das Nachrichten-Menü im Kopfbereich schließt sich jetzt, wenn eine Unterhaltung geöffnet wird. Bisher blieb es offen, weil dabei die Seite nicht gewechselt wird. (!5385)

## Performance
- Navigation zwischen Seiten erfordert kein Neuladen der kompletten Seite. (!4509, !5307 und [weitere](https://gitlab.com/foodsharing-dev/foodsharing/-/merge_requests/?sort=merged_at_desc&state=merged&milestone_title=Quiche%20%F0%9F%A5%A7&label_name%5B%5D=vue%3A%3Arouter))


## Weitere Verbesserung
- Einzelne Pinnwand-Beiträge lassen sich jetzt direkt verlinken: Der Link aus der Benachrichtigung öffnet die Pinnwand, lädt ältere Beiträge bis dorthin nach und hebt den Beitrag hervor. Über das Beitragsmenü lässt sich der Direktlink kopieren. (!5250)
- Wer in eine Arbeitsgruppe aufgenommen wird, kann sofort auf die Gruppe zugreifen - bisher zeigte der Klick auf die Benachrichtigung bis zu sechs Stunden lang eine Fehlermeldung. (!5266)
- Wer ein Thema in einem moderierten Forum erstellt, sieht jetzt einen Hinweis, dass es erst freigeschaltet werden muss - bisher gab es keinerlei Rückmeldung. (!5273)
- Anfragen zum Ändern der E-Mail-Adresse verfallen jetzt nach sieben Tagen; die Bestätigungsmail weist auf die Frist hin. (!5276)
- Beim Melden einer Person kommt jetzt eine Bestätigung mit dem Inhalt der Meldung per E-Mail, die sich beim Melden auch abwählen lässt. Antworten auf diese Mail gehen an die zuständige Melde- oder Schiedsgruppe. (!5280)
- Schlägt ein Bildupload fehl, steht jetzt dabei, woran es lag: zu große Datei, nicht unterstütztes Format wie HEIC vom iPhone, oder ein Netzwerkproblem. (!5298)
- Die Hilfe zum Ressourcen-Mosaik erklärt jetzt, dass Gemeinschaftsressourcen einer übergeordneten Einheit auch in deren Untergruppen erscheinen und nicht mehrfach eingetragen werden müssen. (!5303)
- Ein Blogbeitrag wird jetzt im gleichen Rahmen dargestellt wie der Rest der Seite. Bisher fehlte dem Kasten die Hintergrundfarbe. (!5304)
- Relative Zeitangaben wie "vor 2 Stunden" oder "Heute" werden jetzt in allen unterstützten Sprachen angezeigt statt teils auf Englisch. (!5148)
- Die Abholstatistiken werden ab jetzt täglich so berechnet, dass sich die Abholmenge nicht mehr rückwirkend ändert. (!4565)
- Der Stammbezirk lässt sich über den Dialog jetzt auch dann setzen, wenn man im gewählten Bezirk bereits Mitglied ist (betraf vor allem Wechsler:innen nach dem Verlassen ihres alten Stammbezirks). (!5191)
- Uhrzeiten von Abholungen und Terminen werden jetzt in der Zeit des Betriebs bzw. der Region angezeigt, nicht mehr in der Zeitzone des Geräts. Wer gerade in einer anderen Zeitzone ist, sieht die eigene Zeit in Klammern dazu. Für alle in Mitteleuropa ändert sich nichts. (!5176)
- Die Markdown-Leiste über den Textfeldern in den Betriebseinstellungen sieht jetzt überall gleich aus. (!5284)
- Wird eine Betriebseinladung zurückgezogen, angenommen oder abgelehnt, verschwindet die zugehörige Glocken-Benachrichtigung sofort statt erst nach dem nächsten Neuladen. (!5367)
- Beim Chat an die Betriebsverantwortlichen steht jetzt die Anzahl der Empfänger:innen dabei, sobald es mehr als eine verantwortliche Person gibt. (!5377)



## Weitere Fehlerbehebungen
- Im Gruppen-Überblick zeigt der Tooltip über den Avataren wieder alle weiteren Admins an. (!5248)
- Im dunklen Modus ist der Text im Abo-Menü eines Forumsthemas wieder ausreichend lesbar. (!5249)
- Wer sein Konto löscht, wird sauber abgemeldet. Bisher konnte der Browser mit der alten Sitzung auf einer Fehlerseite landen. (!5267)
- Gelöschte Konten zählen nicht mehr als Wahlberechtigte bei Abstimmungen, und Konto-Löschungen können keine halb gelöschten Datenreste mehr hinterlassen. (!5268)
- Wird ein Bezirk gelöscht, verschwinden jetzt auch seine Termine und sein Postfach. Bisher blieben sie als verwaiste Reste zurück. (!5269)
- Wird ein Ausweis neu ausgedruckt, ohne ihn zu verlängern, behält er sein ursprüngliches Gültigkeitsdatum. (!5274)
- Beim Speichern der Profileinstellungen wird man nicht mehr auf anderen Geräten abgemeldet - das passierte bisher bei jedem Speichern, nicht nur bei Rollenänderungen. (!5275)
- Gelöschte Konten tauchen nicht mehr in der Personensuche auf und können nicht mehr zu Arbeitsgruppen hinzugefügt werden. (!5277)
- Wer über die Bezirksverwaltung als Botschafter*in oder Admin eingesetzt wird, ist sofort Mitglied des Bezirks bzw. der Arbeitsgruppe - nicht erst nach dem nächsten Login oder Nachtlauf. (!5278)
- Wird ein Profil auf Foodsharer zurückgestuft, werden der Entzug der Verifizierung und das Entfernen des Stammbezirks jetzt nachvollziehbar in der Profil-Historie festgehalten. (!5279)
- Scrollen über einer geblätterten Übersicht, etwa den Eintragungsmöglichkeiten auf dem Dashboard, blättert nicht mehr versehentlich zur nächsten Seite. (!5299)
- Wird ein neuer Termin aus einem Bezirk oder einer Arbeitsgruppe heraus angelegt, ist diese Einheit jetzt vorausgewählt. (!5300)
- In der Arbeitsgruppen-Übersicht steht "Mitglied" jetzt nur noch bei Gruppen, in denen man wirklich Mitglied ist. Orgas sahen den Hinweis bisher bei jeder Gruppe. (!5305)
- Meldungen, die zu einem inzwischen gelöschten Betrieb gehören, lassen sich wieder öffnen, statt eine Fehlerseite zu zeigen. (!5322)
- Arbeitsgruppen-Admins können vergebene Auszeichnungen wieder bearbeiten und entziehen. Bisher kam eine Fehlermeldung. (!5342)
- In den Reaktionen auf Pinnwand-Beiträgen steht kein Leerzeichen mehr vor dem Komma zwischen den Namen. (!5351)
- Reine Datumsangaben, etwa "kooperiert seit" oder das Schließdatum in der Karten-Sprechblase, zeigen abends nicht mehr den Vortag an. (!5358)
- Über der Untergruppen-Liste steht wieder der Name der Arbeitsgruppe statt nur der Anzahl. (!5364)
- Der Posteingang im Nutzermenü zeigt den roten Punkt nur noch bei ungelesener Post, und beim Absenden eines Forumsbeitrags blitzt der Nachname nicht mehr kurz auf. (!5366)
- In der Textversion der Mails, die die Plattform verschickt, stehen Links jetzt ohne eckige Klammern. Mailprogramme haben die schließende Klammer bisher mitverlinkt, sodass der Link ins Leere führte. (!5383)
- Fehlt auf einem Server die Twingle-Konfiguration, bleibt die Spendenanzeige jetzt einfach leer, statt einen Serverfehler auszulösen. (!5386)
- Beim Wechsel zwischen zwei Betrieben erscheinen keine Fehlermeldungen mehr, wenn im zweiten Betrieb keine Betriebsverantwortung besteht. (!5326)
- Die Teamliste eines Betriebs lädt wieder, auch wenn ein Mitglied einen unbefristeten oder doppelt vergebenen Hygiene-Nachweis hat. (!5343)
- Im Chat-Fenster bleibt keine Unterhaltung mehr hängen, auf die kein Zugriff mehr besteht. Bisher erschienen dabei zwei rote Fehlermeldungen. (!5372)
- Die Einstellungsseite bleibt nicht mehr leer, wenn die Sprache auf Französisch, Spanisch oder Italienisch eingestellt ist. (!5164)
- Im Inhalte-Editor öffnet sich das Fenster zum Einfügen eines Links wieder innerhalb des Editors. (!5350)

