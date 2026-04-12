Es gibt Neuigkeiten aus der foodsharing-Entwicklung! Das neueste Update, Release Paprika, ist da! 🫑

In den letzten Monaten hat sich einiges getan, und wir haben wieder eine Liste mit den wichtigsten Änderungen für euch zusammengestellt.

Wie immer gab es neben den größeren Veränderungen auch zahlreiche kleinere Verbesserungen, Fehlerbehebungen und technische Optimierungen. Diese führen wir hier nicht alle auf – wer ins Detail gehen möchte, findet im GitLab die komplette Übersicht mit allen Änderungen und den beteiligten Entwickler:innen.

Unser Ziel bleibt es, foodsharing kontinuierlich zu verbessern, damit wir uns noch effizienter gegen Lebensmittelverschwendung organisieren können. Falls euch etwas auffällt oder ihr Feedback habt, schreibt es gerne in diesen Thread: Feedback zum Release Paprika. Und wenn ihr Ideen für zukünftige Updates habt, bringt sie gerne in der überregionalen AG Produktteam ein.

## Account
- Nutzer können nun Zwei-Faktor-Authentisierung (2FA) aktivieren. Nach Aktivierung ist dein Account wesentlich besser geschützt. Wir empfehlen, diese Funktion zu nutzen, um die Sicherheit zu erhöhen. (!4089)
- Der Login wird zwischen beta.foodsharing.de und foodsharing.de synchronisiert. (!4360, !4462, !4480)
- Login per Passkey ist jetzt möglich. Auf Handys außerdem per Fingerabdruck oder FaceID. (!4489)
- Unter https://foodsharing.network/emailverification kann eine neue E-Mail zur Aktivierung der eigenen E-Mail-Adresse angefordert werden. (!4540)
- Der Fehler wurde behoben, dass Accounts mit Vornamen, die mit Umlauten anfangen, nach Abschluss des BV-Quizzes keine Mailbox angelegt wurde.  (!4691)

## Arbeitsgruppen
- Die Seite zum Bearbeiten von Arbeitsgruppen ist jetzt in das Menu der AG-Seite eingegliedert. (!4067)
- Die Links von Profilseiten zu Arbeitsgruppen funktionieren für Orga-Menschen wieder. (!4441)

## Ausweis
- Die Fehlermeldung bei ungültigem Foodsharing-Ausweis wurde überarbeitet und zeigt jetzt auch eine Warnung an, wenn der Ausweis bald abläuft. Die Vorwarnzeit wurde von 30 auf 90 Tage erhöht um ein rechtzeitiges Handeln zu vereinfachen. (!4416)

## Bezirke
- Das Verlassen von Bezirken ist nun nicht mehr zulässig solange man Mitglied in mindestes einem Betrieb ist. Erst wenn man aus allen Betrieben eines Bezirks ausgetreten ist, kann man auch den Bezirk verlassen. Ebenso können Betriebe nicht mehr in Bezirke verlagert werden, in denen nicht alle Teammitglieder bereits Bezirksmitglied sind. Das soll verhindern, dass Nutzer aus Bezirken austreten, in denen sie noch Mitglied in einem Betrieb sind und somit keinen Zugriff mehr auf wichtige Kommunikationsplattformen wie dem Forum haben. (!4788)

## Chat
- Diverse Verbesserungen und Fehlerbehebungen im Chat-System, insbesondere beim Titel von vielen Chat Teilnehmern. (!4449)
- Chats zeigen direkt die Anzahl ungelesener Nachrichten an und können über ihr Menü als ungelesen markiert werden. Chats werden nicht mehr im Hintergrund als gelesen markiert, sondern unter anderem durch Öffnen, Anklicken oder zum Ende Scrollen. (!4786, !4862)
- Beim Öffnen von Chats z.B. "Nachricht an Betriebsverantwortliche" bzw bei Slot-Chats wird jetzt der Chat mit einer Nachricht vorausgefüllt, die allen Beteiligten helfen soll den Kontext des Chats besser zu verstehen. (!4929)

## Forum
- Ausgeblendete Forenbeiträge, die älter als 6 Monate sind, werden jetzt automatisch gelöscht. (!4500)
- Die Mitglieder- und Threadübersicht zeigt das Laden jetzt durch ein Skeleton an (!4637)
- Forenbeiträge können nun vom Ersteller für zehn Minuten nach Erstellung bearbeitet werden. Das soll es ermöglichen, Tippfehler zu korrigieren oder den Beitrag zu ergänzen, ohne dass der Beitrag gelöscht und neu erstellt werden muss. Nach Ablauf der zehn Minuten ist eine Bearbeitung nicht mehr möglich, um die Integrität der Diskussionen zu gewährleisten. Ebenso können Beiträge nicht mehr bearbeitet werden, wenn bereits eine Antwort auf den Beitrag erfolgt ist. (!4911)

## Profil
- Im Profil wird ein Icon angezeigt, wenn der/die Nutzer*in derzeit nicht verifiziert ist. Im Falle einer bestehenden Verifizierung wird nichts zusätzlich angezeigt. Das soll Nutzer*innen dabei helfen, leichter zu erkennen, ob ihr Profil aktuell verifiziert ist oder nicht. Bislang wurde hierfür der Umweg über Ausloggen-öffentliches-Profil-Überprüfen-Einloggen benötigt, was nicht besonders benutzerfreundlich war. (!4514)

## Übersetzung
- Die Webseite ist jetzt auch in portugiesischer Übersetzung verfügbar.  (!4826)

## UI
- Bei geschlossenen Betrieben wird das korrekte Datum angezeigt, sofern verfügbar (!4638)

