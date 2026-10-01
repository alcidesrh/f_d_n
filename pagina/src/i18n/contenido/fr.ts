import type { Contenido } from './tipos'

const fr: Contenido = {
  nosotros: {
    titulo: 'Qui sommes-nous',
    entradilla: 'Depuis 1958, nous relions le Guatemala avec un transport de passagers et de colis sûr, ponctuel et confortable.',
    historia: [
      'Transportes Fuente del Norte a été fondée en 1958 par un homme pionnier et visionnaire qui, malgré l’état des routes, n’a jamais laissé ses efforts être vains.',
      'Tout a commencé avec un pick-up qui transportait des passagers du village de Mariscos jusqu’au carrefour de Trincheras, et un petit car de trois rangées reliant Mariscos à Los Amates et à Puerto Barrios.',
      'En 1964, l’entreprise a acquis la ligne Bananera–Guatemala, une étape qui a exigé beaucoup d’efforts et de dévouement.',
      'En 1969, elle a ouvert la voie vers le Petén et la région atlantique, avec les lignes Guatemala–Puerto Barrios, Santa Elena, Las Cruces et Melchor de Mencos, fruit du travail acharné de don Alfredo Mendoza Pellecer. En 1981, il a réparti les zones de service avec Transportes Litegua : Fuente del Norte a gardé la ligne Guatemala–Petén et Litegua la ligne Guatemala–Puerto Barrios.',
      'Don Alfredo Mendoza a toujours pensé à la famille : il a tenu à transmettre un héritage à ses enfants et à les préparer à porter sa vision plus loin, en pionniers des nouvelles générations.',
      'Cette vision a donné naissance aux services de luxe Classe Or et Maya de Oro. Aujourd’hui, l’entreprise s’est étendue au Guatemala et au-delà : Honduras, Salvador, Belize et frontières mexicaine et bélizienne.',
      'Elle propose des bus de luxe Classe Or à deux niveaux vers le sud et le nord du pays, ainsi qu’une liaison Santa Elena–Cobán, avec les droits et autorisations de la Direction générale des transports.',
      'Elle est aujourd’hui constituée en société anonyme sous le nom de Transportes Fuente del Norte La Pionera, S.A.',
    ],
    secciones: [
      {
        titulo: 'Base légale',
        parrafos: [
          'Entreprise de transport de passagers et de marchandises inscrite au registre du commerce sous le numéro 42723, folio 373, livre 136 des sociétés, le 31 janvier 2000, et enregistrée auprès de la Surintendance de l’administration fiscale (SAT) le 4 février 2000 pour le transport routier de passagers et de marchandises.',
        ],
      },
      {
        titulo: 'Vision',
        parrafos: ['Être une entreprise de transport de passagers et de colis à la pointe de la technologie, garantissant un service de qualité et de confort.'],
      },
      {
        titulo: 'Mission',
        parrafos: [
          'Être leaders du transport national et international de passagers et de colis, en offrant un service de qualité à nos clients, un emploi stable à nos collaborateurs et une contribution au développement social et économique du pays, fondés sur l’efficacité, la satisfaction du client et l’amélioration continue.',
        ],
      },
      {
        titulo: 'Objectifs',
        items: [
          'Offrir le meilleur service de transport de passagers et de colis, en toute sécurité et fiabilité.',
          'Veiller à ce que tout le personnel applique les règles et procédures de l’entreprise dans chaque service.',
          'Garantir efficacité et sécurité pour offrir aux clients le meilleur service possible.',
        ],
      },
      {
        titulo: 'Stratégies',
        items: [
          'Service client : le client avant tout.',
          'De nouveaux bus, pour voyager dans le confort et la sécurité.',
          'De nouveaux services : bus de luxe avec climatisation, télévision et toilettes, et livraison de colis porte-à-porte.',
        ],
      },
      {
        titulo: 'Valeurs',
        items: [
          'Honnêteté : le cœur de toutes nos activités, envers nos clients et chaque personne qui apporte son talent à l’entreprise.',
          'Respect : des personnes et de nos engagements ; c’est ainsi que la qualité de notre service est reconnue.',
          'Travail : la source principale de bénéfices pour tous ; le résultat est la seule mesure de nos efforts.',
          'Technologie : nous l’appliquons et la développons pour rester à la pointe de la qualité.',
        ],
      },
    ],
  },
  servicios: {
    titulo: 'Nos services',
    entradilla: 'Choisissez la façon de voyager qui vous convient, ou envoyez vos colis avec plus de soixante ans d’expérience.',
    lista: [
      {
        id: 'oro-gran-lujo',
        nombre: 'Classe Or Grand Luxe',
        resumen: 'Deux niveaux, Wi-Fi, deux toilettes et niveau inférieur exécutif avec sièges-lits en cuir.',
        texto: 'La flotte Classe Or Grand Luxe est une autre façon de voyager au Guatemala : confort, luxe et divertissement. Un niveau supérieur de 48 sièges tout confort et un niveau inférieur exécutif de 6 sièges-lits en cuir, pour que vous vous reposiez pendant que nous vous conduisons à destination.',
        destacados: ['Wi-Fi', 'Deux toilettes', 'Climatisation', 'Écrans avec écouteurs', 'Prise électrique à chaque siège', 'Deux chauffeurs certifiés', 'Assurance voyageur', 'Régulateur de vitesse automatique'],
      },
      {
        id: 'oro',
        nombre: 'Classe Or',
        resumen: 'Deux niveaux, sièges inclinables et 9 sièges semi-couchettes en bas.',
        texto: 'Idéal pour les voyages d’affaires ou d’agrément. Sièges inclinables avec liseuse individuelle en haut, 9 sièges semi-couchettes en bas pour un trajet plus agréable. En Classe Or, votre voyage est confortable et ponctuel.',
        destacados: ['Climatisation', 'Toilettes', 'Écrans aux deux niveaux', 'Deux chauffeurs certifiés', 'Assurance voyageur', 'Grandes soutes à bagages'],
      },
      {
        id: 'platino',
        nombre: 'Platine',
        resumen: 'Bus modernes de première classe avec sièges inclinables.',
        texto: 'Le même service soigné qui nous distingue, dans des bus modernes et confortables de première classe. Nous vous conduisons à destination rapidement, en sécurité et dans le confort.',
        destacados: ['Sièges inclinables', 'Climatisation automatique', 'Liseuse individuelle', 'Fenêtres panoramiques', 'Assurance voyageur', 'Chauffeurs certifiés'],
      },
      {
        id: 'economico',
        nombre: 'Économique',
        resumen: 'D’une frontière à l’autre, en ménageant votre budget.',
        texto: 'Il vous relie à n’importe quel point du Guatemala dans des bus confortables et sûrs. Pensé pour les familles, le service Économique vous permet de voyager en ménageant votre budget.',
        destacados: ['Sièges individuels', 'Assurance voyageur', 'Présence dans tout le pays'],
      },
      {
        id: 'renta',
        nombre: 'Location de bus et excursions',
        resumen: 'Sorties scolaires, d’affaires, de travail ou de loisirs.',
        texto: 'Les voyages en groupe deviennent une expérience agréable avec un bus Fuente del Norte. Nous nous adaptons à votre budget : bus climatisés tout équipés ou économiques selon votre voyage. Appelez-nous sans engagement.',
        destacados: ['Sur mesure', 'Avec ou sans climatisation', 'Chauffeurs professionnels'],
      },
      {
        id: 'encomiendas',
        nombre: 'Colis',
        resumen: 'Colis et documents de gare à gare, le jour même.',
        texto: 'Envoyez colis, documents et tout ce dont vous avez besoin à vos proches ou à votre entreprise, en toute sécurité, honnêteté, rapidité et ponctualité, avec plus de 60 ans d’expérience. Renseignez-vous dans n’importe quelle gare.',
        destacados: ['Dès Q25.00', 'Départ le jour même', 'Retrait le lendemain dès 8 h'],
      },
    ],
  },
  politicas: {
    titulo: 'Conditions générales',
    entradilla: 'Ce qu’il faut savoir avant de voyager avec nous.',
    secciones: [
      {
        titulo: 'Bagages',
        parrafos: [
          'Vous pouvez transporter 35 livres de bagages. Au-delà, chaque livre supplémentaire coûte Q3.00.',
          'L’entreprise n’est pas responsable de la perte ou des dommages d’objets de valeur, colis fragiles, matériel informatique, électronique ou électroménager : ils restent sous la responsabilité du passager.',
        ],
      },
      {
        titulo: 'Paiement',
        items: [
          'Dans nos gares, vous pouvez payer en espèces ou par carte de crédit ou de débit Visa et Mastercard.',
          'En ligne, vous pouvez payer par carte de crédit ou de débit Visa et Mastercard. Les contrôles de sécurité sont fixés par la banque émettrice (3-D Secure).',
          'Les enfants de moins de 3 ans ne paient pas de siège ; dès 3 ans, ils paient plein tarif.',
          'Tout enfant doit voyager accompagné d’un adulte.',
        ],
      },
      {
        titulo: 'Achats en ligne',
        items: [
          'La vente en ligne ferme avant chaque départ (l’heure est indiquée pour chaque départ). Ensuite, achetez en gare.',
          'Lorsque vous appuyez sur « Payer les sièges », vos sièges sont réservés quelques minutes pendant le paiement. Si vous ne terminez pas, ils sont libérés automatiquement.',
          'Votre billet PDF se télécharge au paiement et vous est envoyé par e-mail avec les données de la facture électronique.',
          'Les billets achetés en ligne ne sont ni remboursables ni modifiables en ligne.',
        ],
      },
      {
        titulo: 'Changements de date',
        parrafos: [
          'Vous pouvez transférer ou changer la date de votre billet jusqu’à 3 heures avant le départ dans toute gare de Transportes Fuente del Norte, en présentant le billet original et une pièce d’identité officielle valide de la personne qui y figure. Passé ce délai, la réaffectation est à la discrétion de l’entreprise.',
        ],
        items: [
          'Impossible de réaffecter s’il n’y a pas de disponibilité ou si les sièges ont des prix différents.',
          'Impossible de réaffecter à moins d’une heure du départ.',
          'Impossible de réaffecter vers le départ d’une autre compagnie : sur les lignes partagées, les compagnies alternent les jours.',
        ],
      },
      {
        titulo: 'Règles d’embarquement',
        parrafos: ['Pour votre sécurité, il est interdit de :'],
        items: [
          'Monter en état d’ivresse ou sous l’effet de drogues.',
          'Porter des armes à feu, armes blanches ou objets tranchants. Si vous en avez, remettez-les au chauffeur, qui vous les rendra à destination.',
          'Monter avec des animaux.',
          'Apporter des boissons alcoolisées ou des drogues.',
          'Transporter de la poudre, des feux d’artifice, des explosifs, des solvants, des bouteilles de gaz, de la peinture ou des produits corrosifs.',
        ],
      },
      {
        titulo: 'Colis',
        items: [
          'Présentez-vous à la gare d’où vous souhaitez envoyer le colis.',
          'Le coût minimum par colis, enveloppe ou document est de Q25.00 ; il varie selon le poids et le volume (le plus élevé est retenu).',
          'Les données de l’expéditeur, du destinataire et du colis sont enregistrées.',
          'Le colis part le jour même avec le dernier départ et peut être retiré le lendemain dès 8 h.',
        ],
      },
    ],
  },
}

export default fr
