<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PartnerPageUpdate extends AbstractMigration
{
    public function change(): void
    {
        $json = '<p>{
            "foodsharing wird unterstützt durch:": [
              {
                "name": "mailbox.org",
                "logo": "https://foodsharing.de/uploads/partner/mailbox192x100px.png",
                "description": "Seit Herbst 2022 vertrauen wir unsere E-Mails dem deutschen E-Mail-Anbieter mailbox.org an. Dieser zeigt, dass sich Digitale Souveränität, Sicherheit und Datenschutz auch mit Komfort, umfangreichen Features und 100% Ökostrom vereinen lassen. Mit dem E-Mail-Postfach erhalten sicherheitsbewusste Kunden auf Basis einer Open Source-Lösung auch Kalender, Adressbuch, Online-Office, einen Cloud-Speicher und eine Videokonferenzlösung. Mehrfach von der Stiftung Warentest mit SEHR GUT ausgezeichnet, setzt mailbox.org für seine Nutzer neue Maßstäbe in sicherer Kommunikation."
              },
              {
                "name": "Geoapify GmbH",
                "logo": "https://foodsharing.de/uploads/partner/Geoapify200x100.png",
                "description": "Schnelle, moderne und kostengünstige APIs für Karten, Adressensuche, Routing und Navigation für Ihre App und Website. Gerne bieten wir zuverlässige Geo- und Standortdienste für jedes Projekt, egal ob groß oder klein."
              },
              {
                "name": "CMS",
                "logo": "https://foodsharing.de/uploads/partner/CMS134x80px.png",
                "description": "Seit Juni 2018 unterstützt uns die Anwaltskanzlei CMS bei überregionalen Angelegenheiten in vielfältigen Themenbereichen wie Lebensmittelrecht, Haftungsfragen, Versicherungs-Recht, Vereinsrecht, Steuerrecht und Datenschutz. Die Anwälte die uns unterstützen sind in ganz Deutschland verteilt, mit einem Schwerpunkt in Köln - wobei die Zusammenarbeit sowieso online erfolgt. Wir freuen uns sehr über diese wichtige Unterstützung, und hoffen auf eine gute und langfristige Zusammenarbeit."
              },
              {
                "name": "PINK CARROTS",
                "logo": "https://foodsharing.de/uploads/partner/pinkcarrots368x100px.jpg",
                "description": "Zum 5 jährigen foodsharing Geburtstag (Dezember 2017) wurde die \"don\'t let good food go bad\" Kampagne gelauncht. Die Kommunikationsagentur PINK CARROTS aus Frankfurt hat das Kampagnen Konzept für foodsharing entwickelt und mit ihrem Know How auch bei dem Entwurf zur neuen Startseite unterstützt. Die Kampagne richtet sich insbesondere an Menschen, die bisher wenig über Lebensmittelverschwendung oder foodsharing wissen. Der humorvolle Auftritt der fünf illustrierten Charaktäre \"walking bread\", \"dick milch\", \"saure gurke\", \"faules ei\" und \"food porn\" wird von zahlreichen Bezirken in Deutschland, Österreich und Schweiz genutzt, um die Message \"don\'t let good food go bad\" weiter zu verbreiten und auf die foodsharing Aktivitäten aufmerksam zu machen."
              },
              {
                "name": "Manitu",
                "logo": "https://foodsharing.de/uploads/partner/manitu390x50px.jpg",
                "description": "Da wir mittlerweile schon über drei Millionen Seitenaufrufe hatten&nbsp;und tagtäglich mehr Menschen die Plattform nutzen, haben wir uns nach einem neuen Partner umgeschaut und einen ganz vorbildlichen Betrieb gefunden der uns mit einem eigenem Server unterstützt. Manitu arbeitet seit Jahren ausschließlich mit Strom aus erneuerbaren Energien und setzt sich ganzheitlich mit einer ethischen Firmenphilosophie für Nachhaltigkeit und mehr Menschlichkeit ein."
              },
              {
                "name": "Greensta Öko Webhosting",
                "logo": "https://foodsharing.de/uploads/partner/greensta390x98px.jpg",
                "description": "Seit Beginn im Sommer 2013 bis zum Herbst 2022 unterstützte Greensta mit einem mit Greenpeace Energy&nbsp;laufenden Server die kostenlose Freiwilligenplattform von foodsharing. Damit gehörte die Unterstützung von dem zu 100 % mit erneuerbaren Energien arbeitenden Firma Greensta zum elementaren Fundament der lebensmittelrettenden Bewegung, die ausschließlich auf unendgeltlichem Engagment fußte. Unser Partner Greensta unterstützte unsere&nbsp;Initiative gegen die Verschwendung von Lebensmitteln mit der Übernahme der Kosten für das Mail-Konto @foodsharing.de."
              },
              {
                "name": "Bitkomplex",
                "logo": "https://foodsharing.de/uploads/partner/bitkomplex_logo2321x488px.png",
                "description": "Von Januar 2019 bis Herbst 2022 hat bitkomplex unseren E-Mail Verkehr für foodsharing.network übernommen. Die Server von Bitkomplex werden ausschließlich mit Ökostrom betrieben."
              },
              {
                "name": "Prototype Fund",
                "logo": "https://foodsharing.de/uploads/partner/Prototype_Fund.jpg",
                "description": "Der Prototype Fund förderte den foodsharing-Programmierer Raphael Wintrich in 2017 für 6 Monate, um die OpenSource-Stellung des aktuellen Codes voran zu bringen."
              }
            ],
            "Akteure im Netzwerk:": [
              {
                "name": "Die Tafeln",
                "logo": "https://foodsharing.de/uploads/partner/Tafel_Deutschland.jpg",
                "description": "Die Tafeln: Lebensmittel retten. Menschen helfen. Seit Anfang an arbeitet foodsharing eng mit den bundesweiten Tafeln zusammen. foodsharing lässt der Tafel bei Kooperationen und Abholungen aufgrund ihres Bedüftigkeits-Anspruches immer den Vortritt. Mit dem starken Nachhaltigkeits-Anspruch und dem Wunsch, der Lebensmittelverschwendung in allen Bereichen entgegen zu wirken, rettet foodsharing dort und zu den Zeitpunkten, wo es der Tafel nicht möglich ist. So bilden beide eine wunderbare Ergänzung, und zusammen ein starkes Team. Mit der offiziellen Kooperationsvereinbarung in 2015 hat dies auch einen formellen Rahmen bekommen, und foodsharing freut sich auf die weitere enge und gute Zusammenarbeit gegen die Lebensmittelverschwendung."
              },
              {
                "name": "Deutsche Umwelthilfe (DUH)",
                "logo": "https://foodsharing.de/uploads/partner/DUH.jpg",
                "description": "Die Deutsche Umwelthilfe (DUH) ist ein anerkannter Umwelt- und Verbraucherschutzverband, der sich seit 1975 aktiv für den Erhalt unserer natürlichen Lebensgrundlagen und die Belange von Verbrauchern einsetzt. Sie ist politisch unabhängig, gemeinnützig, klageberechtigt und engagiert sich vor allem auf nationaler und europäischer Ebene. Kritische Verbraucher, Umweltorganisationen, Politiker, Entscheidungsträger aus der Wirtschaft sowie Medien sind wichtige Partner. Im Bereich Kreislaufwirtschaft setzt sich die DUH für Abfallvermeidung, einen verantwortlichen Konsum und eine nachhaltige Wirtschaftsweise ein."
              }
            ],
            "Wir sind Mitglied bei:": [
              {
                "name": "Wir haben es satt!-Bündnis",
                "logo": "https://foodsharing.de/uploads/partner/wir_haben_es_satt.jpg",
                "description": "Für eine andere Landwirtschaftspolitik Das Bündnis steht für die Agrar- und Ernährungswende. Wir fordern den Stopp der industriellen Landwirtschaft &amp; Lebensmittelproduktion. Wir wollen artgerechte Tierhaltung und gut erzeugte Lebensmittel von Bäuerinnen und Bauern für alle! Dafür gehen wir demonstrieren, bringen Menschen aus Stadt und Land im Dialog und für Aktionen zusammen. Wir sind bunt, vielfältig und wir ziehen an einem Strang - von konventionell bis Bio, von Verbraucher*innen bis Lebensmittelhandwerk. Wir haben es satt! wird von rund 55 Organisationen getragen."
              },
              {
                "name": "Allianz „Rechtssicherheit für politische Willensbildung“",
                "logo": "https://foodsharing.de/uploads/partner/allianzRechtssicherheit.jpg",
                "description": "Wir sind Mitglied der Allianz „Rechtssicherheit für politische Willensbildung“, um gemeinsam mit anderen Organisationen das Gemeinnützigkeitsrecht zu ändern. Zivilgesellschaft ist gemeinnützig – doch Organisationen der Zivilgesellschaft, die sich politisch äußern, sind ständig der Gefahr ausgesetzt, ihre Gemeinnützigkeit zu verlieren. Das wollen wir ändern und Rechtssicherheit schaffen durch gesetzliche Klarstellungen."
              },
              {
                "name": "Klima Allianz",
                "logo": "https://foodsharing.de/uploads/partner/klima_allianz_logo_100x149px.jpg",
                "description": "Die Klima-Allianz Deutschland ist das breite gesellschaftliche Bündnis für den Klimaschutz. Mit über 120 Mitgliedsorganisationen aus den Bereichen Umwelt, Kirche, Entwicklung, Bildung, Kultur, Gesundheit, Verbraucherschutz, Jugend und Gewerkschaften setzt sie sich für eine ambitionierte Klimapolitik und eine erfolgreiche Energiewende auf lokaler, nationaler, europäischer und internationaler Ebene ein. Ihre Mitgliedsorganisationen repräsentieren zusammen rund 25 Millionen Menschen."
              },
              {
                "name": "Bündnis Lebensmittelrettung",
                "logo": "https://foodsharing.de/uploads/partner/BuendnisLebensmittelrettung265x130px.jpg",
                "description": "Wir sind Initiativen, Organisationen, Vereine und Unternehmen, mit dem gemeinsamen Ziel, Lebensmittelverschwendung entlang der gesamten Wertschöpfungskette zu reduzieren. Neben dem vielseitigen Engagement der Bündnispartner, gibt es akuten Handlungsbedarf von Seiten der Politik, um die Lebensmittelverschwendung in den Griff zu bekommen. Das Bündnis ist die politische Stimme gegen Lebensmittelverschwendung in Deutschland."
              },
              {
                "name": "Hand in Hand",
                "logo": "https://foodsharing.de/uploads/partner/HandInHand.png",
                "description": "Als engagierte Personen, Organisationen und Initiativen aus verschiedenen Teilen der Zivilgesellschaft schauen wir der Normalisierung rechter Politiken und Diskurse sowie dem Erstarken der extremen Rechten in Deutschland und Europa nicht länger zu."
              }
            ],
            "Auszeichnungen:": [
              {
                "name": "Braunschweiger Klimaschutzpreis",
                "logo": "https://foodsharing.de/uploads/partner/braunschweig-stadtmarke1500x1100px.jpg",
                "description": "Im Jahr 2017 gewann foodsharing Braunschweig den Klimaschutzpreis der Stadt Braunschweig. Der Preis wird alle zwei Jahre vergeben."
              },
              {
                "name": "WorldSummit Award",
                "logo": "https://foodsharing.de/uploads/partner/wsa.jpg",
                "description": "Gewinner des WSA-Germany 2018 Nominiert für den UN-World Summit Award 2018"
              },
              {
                "name": "Anerkennungspreis «Prix Zug engagiert»",
                "logo": "https://foodsharing.de/uploads/partner/benevolZug731x493px.png",
                "description": "Der Anerkennungspreis «Prix Zug engagiert» wird seit 2011 jährlich vom Kanton Zug und „Benevol Zug“ für besondere Leistungen in der Freiwilligenarbeit verliehen. Diesen Preis gewann foodsharing Zug im Jahr 2019."
              },
              {
                "name": "Umweltschutzpreis Brandenburg an der Havel",
                "logo": "https://foodsharing.de/uploads/partner/BrandenburgAnDerHavel.jpg",
                "description": "Der foodsharing Bezirk Brandenburg an der Havel wurde in den Jahren 2020 und 2024 im Rahmen des Umweltschutzpreises der Stadt Brandenburg an der Havel mit Annerkennung und Preisgeld geehrt."
              },
              {
                "name": "Berliner Lebensmittelretter:innen",
                "logo": "https://foodsharing.de/uploads/partner/BerlinerLebensmittel512x366px.jpg",
                "description": "Das Land Berlin hat einen Preis für das Engagement gegen Lebensmittelverschwendung vergeben. Die Initiative foodsharing Berlin erhielt die Auszeichnung „Berliner Lebensmittelretter:in 2021“. Der von der Senatsverwaltung für Justiz, Verbraucherschutz und Antidiskriminierung ausgelobte Preis ist mit 5.000 Euro dotiert und soll künftig alle zwei Jahre vergeben werden."
              },
              {
                "name": "Super! Energiesparer*innen Wettbewerb",
                "logo": "https://foodsharing.de/uploads/partner/nebenan350x358px.jpg",
                "description": "Der Bezirk Lüneburg konnte 2021 den \"Super! Energiesparer*innen Wettbewerb\" von Nebenan.de gewinnen. Von dem Preisgeld wurde ein E-Lastenbike angeschafft, um nachhaltig große Lebensmittel-Rettungen zu bewältigen."
              },
              {
                "name": "Lichtblick 2023",
                "logo": "https://foodsharing.de/uploads/partner/lichtblick20231800x1600px.jpg",
                "description": "Der Award ehrt Momente, Aktionen und Projekte im Profi- und Amateurfußball aus verschiedenen Bereichen. Im Jahr 2023 gewannen der Karlsruher SC und foodsharing Karlsruhe den Preis in der Kategorie \"ökologische Orientierung\" für ihre Zusammenarbeit im Hospitality-Bereich."
              },
              {
                "name": "Münchener Umweltpreis",
                "logo": "https://foodsharing.de/uploads/partner/munchner_umweltpreis437x199px.jpg",
                "description": "Mit dem Münchner Umweltpreis wird außerordentliches Engagement für Umweltschutz in der Stadt München gewürdigt. Den ersten Platz hat 2023 der foodsharing München e.V. mit seinem Projekt „Lebensmittel retten“ belegt."
              },
              {
                "name": "Umweltpreis der Stadt Erlangen und der Erlanger Stadtwerke",
                "logo": "https://external-content.duckduckgo.com/iu/?u=https%3A%2F%2Fwww.enerpipe.de%2Ffileadmin%2Fdaten%2F04_projekte%2FErlangen%2FErlanger_Stadtwerke_logo.png&amp;f=1&amp;nofb=1&amp;ipt=65c841e5791e5729f5942d9bf3e486ceaca260823cdf94c2af2e03d555365320&amp;ipo=images",
                "description": "Für ihre Bildungs- und Aufklärungsarbeit gegen Lebensmittelverschwendung und für weltweite Ernährungssicherheit erhielt foodsharing Erlangen-Forchheim einen der beiden zweiten Plätze."
              },
              {
                "name": "Umweltpreis der Stadt Deggendorf 2024",
                "logo": "https://foodsharing.de/uploads/partner/UmweltpreisverleihungDeggendorf.jpg",
                "description": "Im Rahmen der Umweltpreis-Verleihung der Stadt Deggendorf erhielt der Bezirk \"foodsharing - Deggendorf\" 2024 eine Anerkennung."
              },
              {
                "name": "Westenergie Klimaschutzpreis",
                "logo": "https://foodsharing.de/uploads/partner/westenergie.jpeg",
                "description": "foodsharing Fürstenau hat den ersten Platz beim Westenergie Klimaschutzpreis gewonnen."
              }
            ],
            "Kooperationsbetriebe:": [
              {
                "name": "Bio Company",
                "logo": "https://foodsharing.de/uploads/partner/10-Logo-Bio-Company.jpg",
                "description": "Die Bio Company ist der erste&nbsp;Partner von foodsharing und hat schon&nbsp;im April&nbsp;2012 begonnen unverkäufliche Lebensmittel an die LebensmittelretterInnen in Berlin zu geben. Auch bei der Crowdfunding Kampagne für foodsharing unterstütze die Bio Supermarkt Kette die Plattfrom mit&nbsp;2000€. Mittlerweile kooperieren&nbsp;die meisten der 35 Filialen neben Tafeln, Vereinen und anderen Einrichtungen mit foodsharing, damit möglichst keine Lebensmittel mehr in die Tonne müssen. Der Geschäftsführer Georg Kaiser ebnete mit seinem Vertrauen und seinem Engagement den Weg für das heutige foodsharing-Netzwerk."
              },
              {
                "name": "Kaufland",
                "logo": "https://foodsharing.de/uploads/partner/Kaufland_online_KL_standard_L_sRGB.png",
                "description": "Die Übernahme von ökologischer und sozialer Verantwortung ist für Kaufland wichtiger Bestandteil der Unternehmenspolitik. Dies beginnt bei einer verantwortungsvollen Gestaltung des Sortiments und setzt sich mit dem Engagement für gesellschaftliche und ökologische Belange fort. Ein besonderes Anliegen ist Kaufland der verantwortungsvolle Umgang mit Lebensmitteln, wozu auch die Vermeidung von Lebensmittelabfällen gehört. Gemeinsam mit foodsharing engagiert sich Kaufland daher aktiv gegen Lebensmittelverschwendung."
              },
              {
                "name": "REWE &amp; PENNY",
                "logo": "https://foodsharing.de/uploads/partner/rewe-penny.png",
                "description": "Die REWE Group engagiert sich seit vielen Jahren dafür, Lebensmittelverschwendung zu minimieren – sowohl in den vorgelagerten Erzeugerstufen als auch in den Märkten. Hier tragen automatisierte Bestellsysteme und moderne Prognoseverfahren ergänzend zu den Erfahrungen der Mitarbeiter zu einer gut ausgesteuerten Warenversorgung bei. Außerdem pflegen die beiden Vertriebslinien REWE und PENNY seit vielen Jahren bundesweit eine enge Partnerschaft mit den lokalen Tafeln. So werden Lebensmittel abgegeben, die nicht mehr verkauft, aber unbedenklich verzehrt werden können, sodass diese gezielt hilfsbedürftigen Menschen zu Gute kommen. Ergänzend dazu kooperieren seit 2021 REWE- und PENNY-Märkte bei Bedarf mit dem Verein foodsharing e.V. und seiner lokalen foodsharing-community, um noch mehr gute Lebensmittel zu retten!"
              },
              {
                "name": "Super Bio Markt",
                "logo": "https://foodsharing.de/uploads/partner/superbiomarkt.jpg",
                "description": "Als Naturkostfachhändler mit tief verwurzelten ökologischen Werten ist es Teil der Unternehmensphilosophie der&nbsp;SuperBioMarkt&nbsp;AG, sich gegen die Verschwendung von Lebensmitteln einzusetzen. Neben weiteren Maßnahmen in diesem Bereich kooperiert das Unternehmen seit Anfang 2014 mit foodsharing. Nach einer erfolgreichen Pilotphase in verschiedenen Städten wird nun die Zusammenarbeit schrittweise auf sämtliche Märkte&nbsp;ausgeweitet. Außerdem unterstützen wir verschiedene Fair-Teiler mit&nbsp;kostenlosen Kühlgeräten."
              },
              {
                "name": "Reformhaus Engelhardt",
                "logo": "https://foodsharing.de/uploads/partner/Reformhaus_Engelhardt1807x751px.png",
                "description": "Das Projekt foodsharing erfährt unsere Unterstützung, weil es auf die Bedeutsamkeit von Lebensmitteln aufmerksam macht und ihnen besondere Wertschätzung entgegenbringt. Das großartige soziale und nachhaltige Engagement, welches die Beteiligten aufbringen ist außerordentlich und sollte eine weitaus größere gesellschaftliche Anerkennung erfahren als bisher. Wir freuen uns auch weiterhin den gemeinsamen Gedanken weiterzutragen und die Projektentwicklung durch unsere Beiträge zu begleiten. In unseren Geschäften werden foodsharing auch in Zukunft die Türen offen sein. Ein verantwortungsbewusster Umgang mit Ressourcen ist in unserer Unternehmensphilosophie fest verankert. Wir integrieren diesen Gedanken in unserer täglichen Arbeit und richten unsere Entscheidungen fest danach aus."
              },
              {
                "name": "Erdkorn",
                "logo": "https://foodsharing.de/uploads/partner/erdkorn.jpg",
                "description": "Die Biomarktkette Erdkorn stellt Lebensmittel, Früchte und Gemüse, die aufgrund des abgelaufenen Mindesthaltbarkeitsdatums bzw. mangelnder Frische in einer Filiale nicht mehr verkauft werden, Foodsavern zum Fairteilen und Verwerten zur Verfügung. Die meisten der deutschlandweit 9 Filialen kooperieren bereits. Hier&nbsp;äußert sich Erdkorn zur Zusammenarbeit und ihrem Engagement gegen die Verschwendung von Lebensmitteln.&nbsp;(Link)"
              },
              {
                "name": "Transgourmet",
                "logo": "https://foodsharing.de/uploads/partner/Transgourmet.png",
                "description": "Unter der Dachmarke Transgourmet Deutschland sind die Spezialisten Transgourmet (Belieferungsgroßhandel) und Selgros (Abholgroßhandel) vertreten. Kunden sind die Gastronomie, Hotellerie, Betriebsverpflegung sowie sozialen Einrichtungen. Seit Jahren setzt sich Transgourmet für die Reduzierung von Lebensmittelverschwendung ein, so zum Beispiel durch den Einsatz von intelligenten Warenwirtschaftssystemen oder den preisreduzierten Abverkauf von Waren mit kurzem MHD. Außerdem bestehen sehr gute Kooperationen mit den Tafeln und anderen sozialen Organisationen. Ergänzend dazu kooperieren die Standorte bei Bedarf auch mit dem Verein foodsharing e.V.. Im Rahmen von Schulungen und Beratungsangeboten unterstützt Transgourmet zudem seine Kunden bei der Reduzierung von Food Waste und trägt so dazu bei, dass auch im Außer-Haus-Markt Lebensmittelabfälle vermieden werden."
              },
              {
                "name": "HORNBACH",
                "logo": "https://foodsharing.de/uploads/partner/Hornbach.png",
                "description": "Bewusste Entscheidungen sind ein wichtiger Schlüssel zur Nachhaltigkeit. Deshalb arbeitet die HORNBACH Baumarkt AG in Deutschland seit Ende 2023 – ganz bewusst – mit foodsharing zusammen, um vielen Produkten ein zweites Leben zu schenken. Gemüsepflanzen, Kräuter, Tierfutter sowie Garten- und Topfpflanzen werden von HORNBACH seither an foodsharing für den guten Zweck abgegeben. Gestartet ist das Pilotprojekt 2023 im Markt Berlin Marzahn. In der Pilotphase konnte das Markt-Team gemeinsam mit foodsharing bereits 13.000 Pflanzen retten. Künftig soll foodsharing auch in weiteren HORNBACH Märkte stattfinden. Nicht nur für die beiden Unternehmen, sondern auch für viele Einrichtungen, denen die abgegebenen Artikel zugutekommen, ist die Zusammenarbeit eine echte Bereicherung."
              },
              {
                "name": "Budnikowsky",
                "logo": "https://foodsharing.de/uploads/partner/budni_300.jpg",
                "description": "Das Hamburger Drogeriemarktunternehmen Budnikowsky unterstützt foodsharing seit 2014 gerne mit Lebensmittelspenden aus verschiedenen BUDNI-Filialen. Mehr zu BUDNIs Engagement: https://budni.de/gutes-tun"
              },
              {
                "name": "Barnhouse",
                "logo": "https://foodsharing.de/uploads/partner/Barnhouse%20Logo%20neu%20color.jpg",
                "description": "Seit unserer Firmengründung vor 36 Jahren sind wir zu 100% dem Bio-Gedanken bei der Herstellung unserer Krunchys verpflichtet. Für uns ist ein biologisch erzeugtes Produkt immer noch das bestmögliche aller Lebensmittel – nie nur Trend oder Mode oder eine Möglichkeit, schnell Geld zu verdienen. Diese Achtung vor Nahrungsmitteln findet sich auch bei foodsharing."
              },
              {
                "name": "CAP-Lebensmittelmärkte",
                "logo": "https://foodsharing.de/uploads/partner/caplogo.png",
                "description": "Seit September 2016 unterstützen die CAP-Lebensmittelmärkte (der NintegrA gGmbH und des Sozialunternehmens Neue Arbeit GmbH) die Initiative foodsharing e.V. Bereits seit über zehn Jahren liefern die CAP-Märkte Lebensmittel mit sehr kurzem Mindesthaltbarkeitsdatum an die Tafeln der Region. Mit der Zusammenarbeit von CAP und foodsharing e.V. gelingt es nun fast vollständig, auf das Wegwerfen von Lebensmitteln zu verzichten. Als diakonisches Unternehmen verpflichtet uns unser Satzungsauftrag zur Bewahrung der Schöpfung und damit einhergehend, alles Erdenkliche gegen Lebensmittelverschwendung zu tun. In unseren Märkten arbeiten mit einem Anteil von mindestens 40 Prozent Menschen mit einer Schwerbehinderung."
              },
              {
                "name": "midsommar festival",
                "logo": "https://foodsharing.de/uploads/partner/mids_schwarz_small500x500px.png",
                "logoDark": "https://saic0.de/u/SmQOTZ.svg",
                "description": "Auf dem midsommar Festival bei München mit Workshops und Independent und elektronischer Musik wird nachhaltig und umweltbewusst gefeiert. Das Festival ist komplett vegan und es wird auf überflüssigen Müll verzichtet."
              },
              {
                "name": "ALDI Süd",
                "logo": "https://foodsharing.de/uploads/partner/AldiSued3357x3600px.png",
                "description": "ALDI – Gutes für alle. Seit mehr als 110 Jahren steht der Name ALDI für Qualität zum besten Preis. Der Discounter ALDI SÜD mit Sitz in Mülheim an der Ruhr betreibt rund 2.000 Filialen in Süd- und Westdeutschland und ist Arbeitgeber von über 50.000 Mitarbeiter:innen. Nachhaltigkeit ist ein Grundpfeiler des unternehmerischen Handelns, was Initiativen wie der #Haltungswechsel für mehr Tierwohl und der #Ernährungswechsel, der eine bewusste Ernährung für alle leistbar macht, immer wieder aufzeigen. Darüber hinaus ist ALDI SÜD Bio-Händler Nr.1 in seinem Verkaufsgebiet und führt über das Jahr verteilt mehr als 1.000 Bio-Artikelsorten. Der verantwortungsvolle Umgang mit Lebensmitteln hat einen hohen Stellenwert bei ALDI SÜD. Daher setzt sich das Unternehmen seit vielen Jahren mithilfe verschiedener Maßnahmen dafür ein, die Verschwendung von Lebensmitteln zu reduzieren. Eine davon ist die Spende von Lebensmitteln die nicht mehr verkäuflich, aber noch genießbar sind. Die ALDI SÜD Filialen spenden Lebensmittel an die Tafel und andere wohltätige Organisationen und unterstützen die Initiative foodsharing."
              }
            ]
          }</p>';
        $this->table('fs_content')
            ->insert([
                [
                    'id' => '93',
                    'name' => 'partner_de',
                    'title' => 'Partner Deutschland',
                    'body' => $json,
                    'last_mod' => '2024-01-17 21:09:00'
                ],
                [
                    'id' => '94',
                    'name' => 'partner_at',
                    'title' => 'Partner Österreich',
                    'body' => $json,
                    'last_mod' => '2024-01-17 21:09:00'
                ],
            ])
            ->save();
    }
}
