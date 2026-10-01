import type { Contenido } from './tipos'

const it: Contenido = {
  nosotros: {
    titulo: 'Chi siamo',
    entradilla: 'Dal 1958 colleghiamo il Guatemala con un trasporto di passeggeri e pacchi sicuro, puntuale e confortevole.',
    historia: [
      'Transportes Fuente del Norte è stata fondata nel 1958 da un uomo pioniere e visionario che, nonostante lo stato delle strade, non lasciò mai che i suoi sforzi andassero sprecati.',
      'Tutto iniziò con un pick-up che portava passeggeri dal villaggio di Mariscos all’incrocio di Trincheras, e un piccolo pulmino a tre file da Mariscos a Los Amates e a Puerto Barrios.',
      'Nel 1964 l’azienda acquisì la linea Bananera–Città del Guatemala, un passo che richiese grande impegno e dedizione.',
      'Nel 1969 aprì la strada verso il Petén e la regione atlantica, con le linee da Città del Guatemala a Puerto Barrios, Santa Elena, Las Cruces e Melchor de Mencos: il frutto del lavoro tenace di don Alfredo Mendoza Pellecer. Nel 1981 concordò con Transportes Litegua le aree di servizio: a Fuente del Norte la linea Guatemala–Petén, a Litegua la linea Guatemala–Puerto Barrios.',
      'Don Alfredo Mendoza pensò sempre alla famiglia: volle lasciare un’eredità ai figli e prepararli a portare la sua visione più lontano, come pionieri delle nuove generazioni.',
      'Da quella visione nacquero i servizi di lusso Classe Oro e Maya de Oro. Oggi l’azienda è presente in tutto il paese e oltre: Honduras, El Salvador, Belize e i confini con Messico e Belize.',
      'Offre autobus di lusso Classe Oro a due piani verso il sud e il nord del paese e un servizio Santa Elena–Cobán, con i relativi diritti e autorizzazioni della Direzione generale dei trasporti.',
      'Oggi è costituita come società per azioni con il nome Transportes Fuente del Norte La Pionera, S.A.',
    ],
    secciones: [
      {
        titulo: 'Base legale',
        parrafos: [
          'Azienda di trasporto di passeggeri e merci iscritta al registro delle imprese con il numero 42723, foglio 373, libro 136 delle società, il 31 gennaio 2000, e registrata presso la Soprintendenza dell’amministrazione tributaria (SAT) il 4 febbraio 2000 per il trasporto stradale di passeggeri e merci.',
        ],
      },
      {
        titulo: 'Visione',
        parrafos: ['Essere un’azienda di trasporto di passeggeri e pacchi all’avanguardia nella tecnologia, che garantisca qualità e comfort.'],
      },
      {
        titulo: 'Missione',
        parrafos: [
          'Essere leader nel trasporto nazionale e internazionale di passeggeri e pacchi, offrendo un servizio di qualità ai clienti, un lavoro stabile ai dipendenti e un contributo allo sviluppo sociale ed economico del paese, basato su efficienza, soddisfazione del cliente e miglioramento continuo.',
        ],
      },
      {
        titulo: 'Obiettivi',
        items: [
          'Offrire il miglior servizio di trasporto di passeggeri e pacchi, in modo sicuro e affidabile.',
          'Assicurare che tutto il personale applichi le norme e le procedure dell’azienda in ogni servizio.',
          'Garantire efficienza e sicurezza per offrire ai clienti il miglior servizio possibile.',
        ],
      },
      {
        titulo: 'Strategie',
        items: [
          'Servizio al cliente: il cliente prima di tutto.',
          'Nuovi autobus, per viaggiare con comodità e sicurezza.',
          'Nuovi servizi: autobus di lusso con aria condizionata, TV e bagno, e consegna dei pacchi porta a porta.',
        ],
      },
      {
        titulo: 'Valori',
        items: [
          'Onestà: il fulcro di tutte le nostre attività, con i clienti e con chiunque porti il suo talento in azienda.',
          'Rispetto: per le persone e per gli impegni presi; così otteniamo il riconoscimento della qualità del servizio.',
          'Lavoro: la fonte principale di benefici per tutti; il risultato è l’unica misura del nostro impegno.',
          'Tecnologia: la applichiamo e la sviluppiamo per restare all’avanguardia nella qualità.',
        ],
      },
    ],
  },
  servicios: {
    titulo: 'I nostri servizi',
    entradilla: 'Scegli il modo di viaggiare più adatto a te, o spedisci i tuoi pacchi con oltre sessant’anni di esperienza.',
    lista: [
      {
        id: 'oro-gran-lujo',
        nombre: 'Classe Oro Gran Lusso',
        resumen: 'Due piani, Wi-Fi, due bagni e piano inferiore executive con sedili letto in pelle.',
        texto: 'La flotta Classe Oro Gran Lusso è un modo diverso di viaggiare in Guatemala: comfort, lusso e intrattenimento. Piano superiore con 48 posti comfort e piano inferiore executive con 6 sedili letto in pelle, per riposare mentre ti portiamo a destinazione.',
        destacados: ['Wi-Fi', 'Due bagni', 'Aria condizionata', 'Schermi con cuffie', 'Presa elettrica a ogni posto', 'Due autisti certificati', 'Assicurazione di viaggio', 'Cruise control automatico'],
      },
      {
        id: 'oro',
        nombre: 'Classe Oro',
        resumen: 'Due piani, sedili reclinabili e 9 posti semi-letto al piano inferiore.',
        texto: 'Ideali per viaggi di lavoro o di piacere. Al piano superiore sedili reclinabili con luce di lettura individuale, al piano inferiore 9 posti semi-letto per un viaggio più piacevole. In Classe Oro il viaggio è comodo e puntuale.',
        destacados: ['Aria condizionata', 'Bagno', 'Schermi su entrambi i piani', 'Due autisti certificati', 'Assicurazione di viaggio', 'Ampi bagagliai'],
      },
      {
        id: 'platino',
        nombre: 'Platino',
        resumen: 'Autobus moderni di prima classe con sedili reclinabili.',
        texto: 'Lo stesso servizio accurato che ci distingue, su autobus moderni e confortevoli di prima classe. Ti portiamo a destinazione con rapidità, sicurezza e comfort.',
        destacados: ['Sedili reclinabili', 'Aria condizionata automatica', 'Luce di lettura individuale', 'Finestrini panoramici', 'Assicurazione di viaggio', 'Autisti certificati'],
      },
      {
        id: 'economico',
        nombre: 'Economica',
        resumen: 'Da confine a confine, risparmiando.',
        texto: 'Ti collega a qualsiasi punto del Guatemala con autobus comodi e sicuri. Pensato per le famiglie, il servizio Economico ti permette di viaggiare risparmiando.',
        destacados: ['Posti singoli', 'Assicurazione di viaggio', 'Presenza in tutto il paese'],
      },
      {
        id: 'renta',
        nombre: 'Noleggio autobus ed escursioni',
        resumen: 'Gite scolastiche, di lavoro, aziendali o di svago.',
        texto: 'I viaggi di gruppo diventano un’esperienza piacevole con un autobus Fuente del Norte. Ci adattiamo al tuo budget: autobus climatizzati e completamente attrezzati o economici, a seconda del viaggio. Chiamaci senza impegno.',
        destacados: ['Su misura', 'Con o senza aria condizionata', 'Autisti professionisti'],
      },
      {
        id: 'encomiendas',
        nombre: 'Pacchi',
        resumen: 'Pacchi e documenti da stazione a stazione, in giornata.',
        texto: 'Spedisci pacchi, documenti e tutto ciò che vuoi mandare ai tuoi cari o alla tua azienda, in modo sicuro, onesto, rapido e puntuale, con oltre 60 anni di esperienza. Chiedi in qualsiasi stazione.',
        destacados: ['Da Q25.00', 'Parte in giornata', 'Ritiro il giorno dopo dalle 8:00'],
      },
    ],
  },
  politicas: {
    titulo: 'Termini e condizioni',
    entradilla: 'Cosa sapere prima di viaggiare con noi.',
    secciones: [
      {
        titulo: 'Bagagli',
        parrafos: [
          'Puoi portare 35 libbre di bagaglio. Ogni libbra in più costa Q3.00.',
          'L’azienda non risponde di perdita o danni a oggetti di valore, scatole fragili, computer, elettronica o elettrodomestici: restano sotto la responsabilità del passeggero.',
        ],
      },
      {
        titulo: 'Pagamento',
        items: [
          'Nelle nostre stazioni puoi pagare in contanti o con carta di credito o di debito Visa e Mastercard.',
          'Online puoi pagare con carta di credito o di debito Visa e Mastercard. I controlli di sicurezza sono stabiliti dalla banca emittente (3-D Secure).',
          'I bambini sotto i 3 anni non pagano il posto; dai 3 anni pagano il biglietto intero.',
          'I bambini devono viaggiare accompagnati da un adulto.',
        ],
      },
      {
        titulo: 'Acquisti online',
        items: [
          'La vendita online chiude prima di ogni partenza (l’orario è indicato per ogni corsa). Dopo, acquista in stazione.',
          'Quando premi «Paga i posti», i posti restano riservati per alcuni minuti mentre paghi. Se non completi l’acquisto, si liberano da soli.',
          'Il biglietto in PDF si scarica al pagamento e arriva via e-mail con i dati della fattura elettronica.',
          'I biglietti acquistati online non sono rimborsabili né modificabili online.',
        ],
      },
      {
        titulo: 'Cambi di data',
        parrafos: [
          'Puoi trasferire o cambiare la data del biglietto fino a 3 ore prima della partenza in qualsiasi stazione di Transportes Fuente del Norte, presentando il biglietto originale e un documento d’identità valido della persona indicata. Dopo, la riassegnazione è a discrezione dell’azienda.',
        ],
        items: [
          'Non è possibile riassegnare se non c’è disponibilità o se i posti hanno prezzi diversi.',
          'Non è possibile riassegnare a meno di un’ora dalla partenza.',
          'Non è possibile riassegnare alla corsa di un’altra compagnia: sulle linee condivise le compagnie si alternano nei giorni.',
        ],
      },
      {
        titulo: 'Regole d’imbarco',
        parrafos: ['Per la tua sicurezza non è consentito:'],
        items: [
          'Salire in stato di ebbrezza o sotto l’effetto di droghe.',
          'Portare armi da fuoco, armi da taglio o oggetti appuntiti. Se ne hai, consegnali all’autista, che te li restituirà a destinazione.',
          'Salire con animali.',
          'Portare bevande alcoliche o droghe.',
          'Trasportare polvere da sparo, fuochi d’artificio, esplosivi, solventi, bombole di gas, vernici o sostanze corrosive.',
        ],
      },
      {
        titulo: 'Pacchi',
        items: [
          'Presentati alla stazione da cui vuoi spedire il pacco.',
          'Il costo minimo per pacco, busta o documento è Q25.00; varia in base a peso e volume (si considera il maggiore).',
          'Si registrano i dati del mittente, del destinatario e del pacco.',
          'Il pacco parte in giornata con l’ultima corsa e si ritira il giorno dopo dalle 8:00.',
        ],
      },
    ],
  },
}

export default it
