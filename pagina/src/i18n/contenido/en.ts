import type { Contenido } from './tipos'

const en: Contenido = {
  nosotros: {
    titulo: 'About us',
    entradilla: 'Since 1958 we have connected Guatemala with safe, punctual and comfortable passenger and parcel transport.',
    historia: [
      'Transportes Fuente del Norte was founded in 1958 by a pioneering, visionary man who, despite the state of the roads, never let his efforts go to waste.',
      'It started with a pick-up truck carrying passengers from the village of Mariscos to the Trincheras junction, and a three-row minibus running from Mariscos to Los Amates and from Mariscos to Puerto Barrios.',
      'In 1964 the company acquired the Bananera–Guatemala City line, a step that took great effort and dedication.',
      'In 1969 it opened the way into Petén and the Atlantic region, with lines from Guatemala City to Puerto Barrios, Santa Elena, Las Cruces and Melchor de Mencos — the fruit of the hard, tenacious work of Don Alfredo Mendoza Pellecer. In 1981 he agreed service areas with Transportes Litegua: Fuente del Norte kept the Guatemala–Petén line and Litegua the Guatemala–Puerto Barrios line.',
      'Don Alfredo Mendoza always thought of family: he made sure to leave a legacy to his children and prepared them to carry his vision further, as pioneers of the new generations.',
      'That vision gave rise to the luxury Gold Class and Maya de Oro bus services. Today the company has expanded within the country and beyond, to Honduras, El Salvador, Belize and the Mexican and Belizean borders.',
      'It runs luxury double-decker Gold Class buses to the south and north of the country, and a Santa Elena–Cobán service, with the corresponding rights and permits from the Directorate General of Transport.',
      'It is currently incorporated as Transportes Fuente del Norte La Pionera, S.A.',
    ],
    secciones: [
      {
        titulo: 'Legal basis',
        parrafos: [
          'Passenger and freight transport company registered with a company trade licence under registration number 42723, folio 373, book 136 of companies, on 31 January 2000, and registered with the Tax Administration Superintendency (SAT) on 4 February 2000 for road passenger and freight transport.',
        ],
      },
      {
        titulo: 'Vision',
        parrafos: ['To be a passenger and parcel transport company at the forefront of technology, guaranteeing quality and comfortable service.'],
      },
      {
        titulo: 'Mission',
        parrafos: [
          'To lead national and international passenger and parcel transport, offering quality service to our customers, stable jobs to our employees and a contribution to the social and economic development of the country, based on efficiency, customer satisfaction and continuous improvement.',
        ],
      },
      {
        titulo: 'Goals',
        items: [
          'Offer the best passenger and parcel transport service, safely and reliably.',
          'Make sure all staff follow the company’s rules and procedures in every service.',
          'Guarantee efficiency and safety so customers get the best possible service.',
        ],
      },
      {
        titulo: 'Strategies',
        items: [
          'Customer service: the customer above all.',
          'New buses, for comfortable and safe travel.',
          'New services: luxury buses with air conditioning, TV and restroom, and door-to-door parcel delivery.',
        ],
      },
      {
        titulo: 'Values',
        items: [
          'Honesty: the core of everything we do, with customers and with everyone who brings their talent to the company.',
          'Respect: for people and for our commitments; that is how we earn recognition for the quality of our service.',
          'Work: the main source of benefits for everyone; results are the only measure of our effort.',
          'Technology: we apply and develop it to stay at the forefront of quality and service.',
        ],
      },
    ],
  },
  servicios: {
    titulo: 'Our services',
    entradilla: 'Choose the way of travelling that suits you best, or send your parcels backed by more than six decades of experience.',
    lista: [
      {
        id: 'oro-gran-lujo',
        nombre: 'Gold Class Grand Luxury',
        resumen: 'Double-decker with Wi-Fi, two restrooms and an executive lower deck with leather sleeper seats.',
        texto: 'The Gold Class Grand Luxury fleet is a different way to travel through Guatemala: comfort, luxury and entertainment. An upper deck with 48 fully comfortable seats and an executive lower deck with 6 leather sleeper seats, so you can rest while we take you to your destination.',
        destacados: ['Wi-Fi', 'Two restrooms', 'Air conditioning', 'Screens with headphones', 'Power outlet at every seat', 'Two certified drivers', 'Travel insurance', 'Automatic cruise control'],
      },
      {
        id: 'oro',
        nombre: 'Gold Class',
        resumen: 'Double-decker with reclining seats and 9 semi-sleeper seats downstairs.',
        texto: 'Ideal for business or leisure trips. Reclining seats with individual reading lights upstairs and 9 semi-sleeper seats downstairs for a more pleasant journey. With Gold Class your trip is comfortable and on time.',
        destacados: ['Air conditioning', 'Restroom', 'Screens on both decks', 'Two certified drivers', 'Travel insurance', 'Large luggage holds'],
      },
      {
        id: 'platino',
        nombre: 'Platinum',
        resumen: 'Modern first-class buses with reclining seats.',
        texto: 'The same careful service we are known for, in modern and comfortable first-class buses. We take you to your destination promptly, safely and in comfort.',
        destacados: ['Reclining seats', 'Automatic air conditioning', 'Individual reading lights', 'Panoramic windows', 'Travel insurance', 'Certified drivers'],
      },
      {
        id: 'economico',
        nombre: 'Economy',
        resumen: 'From border to border, at a price that suits your budget.',
        texto: 'It connects you to any point in Guatemala in comfortable and safe buses. With families in mind, our Economy service lets you travel while looking after your budget.',
        destacados: ['Individual seats', 'Travel insurance', 'Nationwide coverage'],
      },
      {
        id: 'renta',
        nombre: 'Bus rental and excursions',
        resumen: 'School, business, work or leisure trips.',
        texto: 'Group travel becomes a pleasant experience with a Fuente del Norte bus. We adapt to your budget: fully equipped air-conditioned buses or economy units, depending on your trip. Call us with no obligation.',
        destacados: ['Tailored to you', 'With or without air conditioning', 'Professional drivers'],
      },
      {
        id: 'encomiendas',
        nombre: 'Parcels',
        resumen: 'Parcels and documents from station to station, same day.',
        texto: 'Send parcels, documents and anything you need to your family or company safely, honestly, quickly and on time, backed by over 60 years of experience. Ask at any station.',
        destacados: ['From Q25.00', 'Ships the same day', 'Pick up the next day from 8:00 a.m.'],
      },
    ],
  },
  politicas: {
    titulo: 'Terms and conditions',
    entradilla: 'What you need to know before travelling with us.',
    secciones: [
      {
        titulo: 'Luggage',
        parrafos: [
          'You may carry 35 pounds of luggage. Excess weight costs Q3.00 per extra pound.',
          'The company is not responsible for loss of or damage to valuables, fragile boxes, computers, electronics or appliances: they remain the passenger’s responsibility.',
        ],
      },
      {
        titulo: 'Payment',
        items: [
          'At our stations you can pay in cash or by Visa or Mastercard credit or debit card.',
          'Online you can pay by Visa or Mastercard credit or debit card. Security checks are set by the issuing bank (3-D Secure).',
          'Children under 3 do not pay for a seat; children 3 and over pay full fare.',
          'All children must travel with an adult.',
        ],
      },
      {
        titulo: 'Online purchases',
        items: [
          'Online sales close before each departure (the time is shown for every departure). After that, buy at the station.',
          'When you press “Pay for seats”, your seats are held for a few minutes while you pay. If you do not finish, they are released automatically.',
          'Your PDF ticket downloads when you pay and is emailed to you with the electronic invoice details.',
          'Tickets bought online cannot be refunded or changed online.',
        ],
      },
      {
        titulo: 'Date changes',
        parrafos: [
          'You may transfer or change the date of your ticket up to 3 hours before departure at any Transportes Fuente del Norte station, presenting the original ticket and valid official ID of the person named on it. After that, reassignment is at the company’s discretion.',
        ],
        items: [
          'Reassignment is not possible if there is no availability or the seats have different prices.',
          'Reassignment is not possible less than one hour before departure.',
          'Reassignment to another company’s departure is not possible: on shared routes, companies alternate days.',
        ],
      },
      {
        titulo: 'Boarding policies',
        parrafos: ['For your safety, it is not allowed to:'],
        items: [
          'Board while drunk or showing signs of drug use.',
          'Carry firearms, knives or sharp objects. If you carry any, hand it to the driver, who will return it at your destination.',
          'Bring animals on the bus.',
          'Bring alcoholic drinks or drugs.',
          'Carry gunpowder or fireworks, explosives, solvents, gas tanks, paint or corrosives.',
        ],
      },
      {
        titulo: 'Parcels',
        items: [
          'Go to the station you want to send the parcel from.',
          'The minimum cost per parcel, envelope or document is Q25.00; it varies with weight and volume (whichever is greater).',
          'Sender, recipient and parcel details are recorded.',
          'Parcels ship the same day on the last run and can be picked up the next day from 8:00 a.m.',
        ],
      },
    ],
  },
}

export default en
