import type { Contenido } from './tipos'

const de: Contenido = {
  nosotros: {
    titulo: 'Über uns',
    entradilla: 'Seit 1958 verbinden wir Guatemala mit sicherem, pünktlichem und bequemem Personen- und Pakettransport.',
    historia: [
      'Transportes Fuente del Norte wurde 1958 von einem Pionier und Visionär gegründet, der trotz der schlechten Straßen seine Mühen nie vergebens sein ließ.',
      'Alles begann mit einem Pick-up, der Fahrgäste vom Dorf Mariscos zur Kreuzung Trincheras brachte, und einem Kleinbus mit drei Sitzreihen von Mariscos nach Los Amates und nach Puerto Barrios.',
      '1964 übernahm das Unternehmen die Linie Bananera–Guatemala-Stadt, ein Schritt, der viel Einsatz erforderte.',
      '1969 erschloss es den Petén und die Atlantikregion mit Linien von Guatemala-Stadt nach Puerto Barrios, Santa Elena, Las Cruces und Melchor de Mencos – das Ergebnis der beharrlichen Arbeit von Don Alfredo Mendoza Pellecer. 1981 vereinbarte er mit Transportes Litegua die Aufteilung: Fuente del Norte behielt die Linie Guatemala–Petén, Litegua die Linie Guatemala–Puerto Barrios.',
      'Don Alfredo Mendoza dachte stets an die Familie: Er wollte seinen Kindern ein Erbe hinterlassen und sie darauf vorbereiten, seine Vision als Pioniere der neuen Generationen weiterzutragen.',
      'Aus dieser Vision entstanden die Luxusdienste Goldklasse und Maya de Oro. Heute ist das Unternehmen im ganzen Land und darüber hinaus tätig: in Honduras, El Salvador, Belize und an den Grenzen zu Mexiko und Belize.',
      'Es bietet Doppeldecker-Luxusbusse der Goldklasse in den Süden und Norden des Landes sowie eine Verbindung Santa Elena–Cobán, mit den entsprechenden Rechten und Genehmigungen der Generaldirektion für Verkehr.',
      'Heute firmiert es als Aktiengesellschaft unter dem Namen Transportes Fuente del Norte La Pionera, S.A.',
    ],
    secciones: [
      {
        titulo: 'Rechtsgrundlage',
        parrafos: [
          'Unternehmen für Personen- und Güterbeförderung, im Handelsregister unter Nummer 42723, Folio 373, Buch 136 der Gesellschaften am 31. Januar 2000 eingetragen und am 4. Februar 2000 bei der Steuerbehörde (SAT) für den Personen- und Güterverkehr auf der Straße registriert.',
        ],
      },
      {
        titulo: 'Vision',
        parrafos: ['Ein Unternehmen für Personen- und Pakettransport an der Spitze der Technik zu sein, das Qualität und Komfort garantiert.'],
      },
      {
        titulo: 'Mission',
        parrafos: [
          'Marktführer im nationalen und internationalen Personen- und Pakettransport zu sein: mit Qualitätsservice für unsere Kunden, sicheren Arbeitsplätzen für unsere Mitarbeitenden und einem Beitrag zur sozialen und wirtschaftlichen Entwicklung des Landes – auf Basis von Effizienz, Kundenzufriedenheit und ständiger Verbesserung.',
        ],
      },
      {
        titulo: 'Ziele',
        items: [
          'Den besten Personen- und Pakettransport bieten, sicher und zuverlässig.',
          'Dafür sorgen, dass alle Mitarbeitenden die Regeln und Abläufe des Unternehmens einhalten.',
          'Effizienz und Sicherheit gewährleisten, damit unsere Kunden den bestmöglichen Service erhalten.',
        ],
      },
      {
        titulo: 'Strategien',
        items: [
          'Kundenservice: Der Kunde steht an erster Stelle.',
          'Neue Busse für bequemes und sicheres Reisen.',
          'Neue Leistungen: Luxusbusse mit Klimaanlage, Fernsehen und Toilette sowie Paketzustellung von Tür zu Tür.',
        ],
      },
      {
        titulo: 'Werte',
        items: [
          'Ehrlichkeit: der Kern all unserer Tätigkeiten, gegenüber Kunden und allen, die ihr Talent einbringen.',
          'Respekt: vor Menschen und unseren Verpflichtungen; so gewinnen wir Anerkennung für unsere Qualität.',
          'Arbeit: die wichtigste Quelle des Nutzens für alle; nur das Ergebnis zählt.',
          'Technologie: Wir setzen sie ein und entwickeln sie weiter, um bei Qualität und Service vorn zu sein.',
        ],
      },
    ],
  },
  servicios: {
    titulo: 'Unsere Leistungen',
    entradilla: 'Wählen Sie die Reiseart, die zu Ihnen passt, oder versenden Sie Pakete mit über sechzig Jahren Erfahrung.',
    lista: [
      {
        id: 'oro-gran-lujo',
        nombre: 'Goldklasse Grand Luxe',
        resumen: 'Doppeldecker mit WLAN, zwei Toiletten und Executive-Unterdeck mit Liegesitzen aus Leder.',
        texto: 'Die Flotte der Goldklasse Grand Luxe ist eine besondere Art, Guatemala zu bereisen: Komfort, Luxus und Unterhaltung. Oberdeck mit 48 Komfortsitzen und Executive-Unterdeck mit 6 Leder-Liegesitzen – Sie ruhen sich aus, während wir Sie ans Ziel bringen.',
        destacados: ['WLAN', 'Zwei Toiletten', 'Klimaanlage', 'Bildschirme mit Kopfhörern', 'Steckdose an jedem Sitz', 'Zwei zertifizierte Fahrer', 'Reiseversicherung', 'Automatischer Tempomat'],
      },
      {
        id: 'oro',
        nombre: 'Goldklasse',
        resumen: 'Doppeldecker mit Liegesitzen und 9 Halbschlafsitzen im Unterdeck.',
        texto: 'Ideal für Geschäfts- oder Urlaubsreisen. Oben verstellbare Sitze mit Leselicht, unten 9 Halbschlafsitze für eine angenehmere Fahrt. In der Goldklasse reisen Sie bequem und pünktlich.',
        destacados: ['Klimaanlage', 'Toilette', 'Bildschirme auf beiden Decks', 'Zwei zertifizierte Fahrer', 'Reiseversicherung', 'Große Gepäckräume'],
      },
      {
        id: 'platino',
        nombre: 'Platin',
        resumen: 'Moderne Erste-Klasse-Busse mit verstellbaren Sitzen.',
        texto: 'Der gleiche sorgfältige Service, der uns auszeichnet, in modernen und bequemen Bussen der ersten Klasse. Wir bringen Sie schnell, sicher und komfortabel ans Ziel.',
        destacados: ['Verstellbare Sitze', 'Automatische Klimaanlage', 'Leselicht', 'Panoramafenster', 'Reiseversicherung', 'Zertifizierte Fahrer'],
      },
      {
        id: 'economico',
        nombre: 'Economy',
        resumen: 'Von Grenze zu Grenze, schonend für Ihr Budget.',
        texto: 'Verbindet Sie mit jedem Ort in Guatemala in bequemen und sicheren Bussen. Für Familien gedacht: Mit Economy reisen Sie und schonen Ihr Budget.',
        destacados: ['Einzelsitze', 'Reiseversicherung', 'Im ganzen Land'],
      },
      {
        id: 'renta',
        nombre: 'Busvermietung und Ausflüge',
        resumen: 'Schul-, Geschäfts-, Betriebs- oder Freizeitausflüge.',
        texto: 'Gruppenreisen werden mit einem Bus von Fuente del Norte zum Erlebnis. Wir richten uns nach Ihrem Budget: voll ausgestattete Busse mit Klimaanlage oder günstige Fahrzeuge, je nach Reise. Rufen Sie uns unverbindlich an.',
        destacados: ['Nach Maß', 'Mit oder ohne Klimaanlage', 'Professionelle Fahrer'],
      },
      {
        id: 'encomiendas',
        nombre: 'Pakete',
        resumen: 'Pakete und Dokumente von Station zu Station, am selben Tag.',
        texto: 'Versenden Sie Pakete, Dokumente und alles, was Sie Ihrer Familie oder Firma schicken möchten – sicher, ehrlich, schnell und pünktlich, mit über 60 Jahren Erfahrung. Fragen Sie an jeder Station.',
        destacados: ['Ab Q25.00', 'Versand am selben Tag', 'Abholung am Folgetag ab 8:00 Uhr'],
      },
    ],
  },
  politicas: {
    titulo: 'Geschäftsbedingungen',
    entradilla: 'Was Sie vor der Reise mit uns wissen sollten.',
    secciones: [
      {
        titulo: 'Gepäck',
        parrafos: [
          'Sie dürfen 35 Pfund Gepäck mitnehmen. Jedes zusätzliche Pfund kostet Q3.00.',
          'Das Unternehmen haftet nicht für Verlust oder Beschädigung von Wertsachen, zerbrechlichen Kisten, Computern, Elektronik oder Haushaltsgeräten: Sie bleiben in der Verantwortung des Fahrgasts.',
        ],
      },
      {
        titulo: 'Bezahlung',
        items: [
          'An unseren Stationen zahlen Sie bar oder mit Visa- und Mastercard-Kredit- oder Debitkarte.',
          'Online zahlen Sie mit Visa- oder Mastercard-Kredit- oder Debitkarte. Die Sicherheitsprüfung legt die ausgebende Bank fest (3-D Secure).',
          'Kinder unter 3 Jahren zahlen keinen Sitzplatz; ab 3 Jahren gilt der volle Fahrpreis.',
          'Kinder reisen nur in Begleitung eines Erwachsenen.',
        ],
      },
      {
        titulo: 'Online-Käufe',
        items: [
          'Der Online-Verkauf endet vor jeder Abfahrt (die Uhrzeit steht bei jeder Fahrt). Danach kaufen Sie an der Station.',
          'Wenn Sie auf „Sitze bezahlen“ tippen, werden Ihre Sitze einige Minuten für die Zahlung reserviert. Schließen Sie nicht ab, werden sie automatisch freigegeben.',
          'Ihr PDF-Ticket wird beim Bezahlen heruntergeladen und mit den Daten der elektronischen Rechnung per E-Mail gesendet.',
          'Online gekaufte Tickets können online weder erstattet noch geändert werden.',
        ],
      },
      {
        titulo: 'Umbuchung',
        parrafos: [
          'Sie können Ihr Ticket bis 3 Stunden vor Abfahrt an jeder Station von Transportes Fuente del Norte übertragen oder umbuchen, gegen Vorlage des Original-Tickets und eines gültigen amtlichen Ausweises der darauf genannten Person. Danach liegt die Umbuchung im Ermessen des Unternehmens.',
        ],
        items: [
          'Keine Umbuchung, wenn keine Plätze frei sind oder die Sitze unterschiedliche Preise haben.',
          'Keine Umbuchung weniger als eine Stunde vor Abfahrt.',
          'Keine Umbuchung auf die Fahrt eines anderen Unternehmens: Auf geteilten Strecken wechseln sich die Unternehmen tageweise ab.',
        ],
      },
      {
        titulo: 'Einstiegsregeln',
        parrafos: ['Zu Ihrer Sicherheit ist nicht erlaubt:'],
        items: [
          'Betrunken oder unter Drogeneinfluss einzusteigen.',
          'Schusswaffen, Messer oder spitze Gegenstände mitzuführen. Geben Sie diese dem Fahrer, der sie Ihnen am Ziel zurückgibt.',
          'Tiere mitzunehmen.',
          'Alkohol oder Drogen mitzubringen.',
          'Schießpulver, Feuerwerk, Sprengstoff, Lösungsmittel, Gasflaschen, Farben oder ätzende Stoffe zu befördern.',
        ],
      },
      {
        titulo: 'Pakete',
        items: [
          'Kommen Sie zu der Station, von der Sie das Paket senden möchten.',
          'Der Mindestpreis pro Paket, Umschlag oder Dokument beträgt Q25.00; er richtet sich nach Gewicht und Volumen (der höhere Wert zählt).',
          'Absender-, Empfänger- und Paketdaten werden erfasst.',
          'Das Paket fährt am selben Tag mit der letzten Fahrt und kann am Folgetag ab 8:00 Uhr abgeholt werden.',
        ],
      },
    ],
  },
}

export default de
