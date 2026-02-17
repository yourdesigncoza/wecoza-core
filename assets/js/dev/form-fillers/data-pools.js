/**
 * WeCoza Dev Toolbar — South African Test Data Pools
 *
 * Realistic SA data for auto-filling forms during development/testing.
 * Only loaded when WP_DEBUG is true.
 */
window.WeCozaDevData = {

    // ── Names ──────────────────────────────────────────────
    titles: ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'],

    firstNamesMale: [
        'Sipho', 'Thabo', 'Johan', 'Pieter', 'Andile', 'Bongani', 'David',
        'Lebogang', 'Ntando', 'Francois', 'Jacques', 'Mandla', 'Tshepo',
        'Willem', 'Ruan', 'Vusi', 'Sifiso', 'Heinrich', 'Kagiso', 'Tumelo'
    ],

    firstNamesFemale: [
        'Thandiwe', 'Naledi', 'Zanele', 'Lindiwe', 'Nomsa', 'Lerato',
        'Precious', 'Anele', 'Palesa', 'Cynthia', 'Fikile', 'Nonhlanhla',
        'Amahle', 'Minenhle', 'Yolandi', 'Chantel', 'Busisiwe', 'Khanyi',
        'Nokuthula', 'Refilwe'
    ],

    surnames: [
        'Nkosi', 'Van der Merwe', 'Dlamini', 'Botha', 'Mkhize', 'Ndlovu',
        'Pretorius', 'Zulu', 'Molefe', 'Du Plessis', 'Khumalo', 'Govender',
        'Pillay', 'Van Wyk', 'Sithole', 'Mokoena', 'Mahlangu', 'Joubert',
        'Ngcobo', 'Swanepoel', 'Maseko', 'Baloyi', 'Le Roux', 'Motaung'
    ],

    // ── Contact ────────────────────────────────────────────
    phonePrefixes: [
        '060', '061', '062', '063', '064', '065',
        '071', '072', '073', '074', '076', '078', '079',
        '081', '082', '083', '084'
    ],

    emailDomains: ['testmail.co.za', 'devtest.org.za', 'example.co.za'],

    // ── Address ────────────────────────────────────────────
    streets: [
        '12 Main Road', '45 Voortrekker Street', '78 Church Street',
        '23 Long Street', '56 Jan Smuts Avenue', '89 Nelson Mandela Drive',
        '34 Commissioner Street', '67 Oxford Road', '11 Rivonia Road',
        '9 Beyers Naude Drive', '15 Ontdekkers Road', '42 William Nicol Drive',
        '3 Sandton Drive', '28 Hendrik Verwoerd Drive', '51 Paul Kruger Street'
    ],

    suburbs: [
        'Sandton', 'Rosebank', 'Braamfontein', 'Hatfield', 'Menlyn',
        'Umhlanga', 'Claremont', 'Stellenbosch', 'Bellville', 'Centurion',
        'Midrand', 'Fourways', 'Randburg', 'Bedfordview', 'Germiston'
    ],

    towns: [
        'Johannesburg', 'Pretoria', 'Cape Town', 'Durban', 'Port Elizabeth',
        'Bloemfontein', 'Polokwane', 'Nelspruit', 'Kimberley', 'East London',
        'Pietermaritzburg', 'Rustenburg', 'Soweto', 'Middelburg', 'Witbank'
    ],

    provinces: [
        'Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal',
        'Limpopo', 'Mpumalanga', 'Northern Cape', 'North West', 'Western Cape'
    ],

    postalCodes: [
        '2196', '2001', '0001', '4001', '6001', '9301',
        '0700', '1200', '8001', '5201', '3200', '0300'
    ],

    // ── SA Coordinates (approx city centres) ───────────────
    coordinates: [
        { lat: '-26.2041', lng: '28.0473' },  // Johannesburg
        { lat: '-25.7479', lng: '28.2293' },  // Pretoria
        { lat: '-33.9249', lng: '18.4241' },  // Cape Town
        { lat: '-29.8587', lng: '31.0218' },  // Durban
        { lat: '-33.9608', lng: '25.6022' },  // Port Elizabeth
        { lat: '-29.0852', lng: '26.1596' },  // Bloemfontein
        { lat: '-23.9045', lng: '29.4689' },  // Polokwane
        { lat: '-25.4753', lng: '30.9694' },  // Nelspruit
    ],

    // ── Business ───────────────────────────────────────────
    companyNames: [
        'Protea Holdings', 'Baobab Solutions', 'Springbok Industries',
        'Ubuntu Technologies', 'Fynbos Group', 'Impala Resources',
        'Kudu Logistics', 'Marula Consulting', 'Shaka Enterprises',
        'Biltong Bros Trading', 'Mandela Foundation Corp', 'Table Mountain Media'
    ],

    companyRegistrations: [
        '2020/123456/07', '2019/654321/07', '2021/111222/07',
        '2018/333444/07', '2022/555666/07', '2017/777888/07'
    ],

    setas: [
        'BANKSETA', 'CATHSSETA', 'CHIETA', 'ETDP SETA', 'EWSETA',
        'FASSET', 'FOODBEV', 'HWSETA', 'INSETA', 'LGSETA',
        'MERSETA', 'MICT SETA', 'MQA', 'PSETA', 'SASSETA',
        'SERVICES SETA', 'TETA', 'W&RSETA', 'AgriSETA', 'CETA'
    ],

    clientStatuses: ['Active', 'Inactive', 'Pending'],

    // ── Banking ────────────────────────────────────────────
    banks: [
        { name: 'ABSA', branchCode: '632005' },
        { name: 'FNB', branchCode: '250655' },
        { name: 'Standard Bank', branchCode: '051001' },
        { name: 'Nedbank', branchCode: '198765' },
        { name: 'Capitec', branchCode: '470010' },
        { name: 'Investec', branchCode: '580105' }
    ],

    accountTypes: ['Savings', 'Current', 'Transmission'],

    // ── Education & Qualifications ─────────────────────────
    qualifications: [
        'National Senior Certificate', 'Bachelor of Education',
        'Bachelor of Arts', 'Bachelor of Commerce', 'Diploma in Education',
        'Advanced Certificate in Teaching', 'PGCE', 'Master of Education',
        'National Diploma', 'Higher Certificate'
    ],

    subjects: [
        'Mathematics', 'English', 'Science', 'Afrikaans', 'Life Skills',
        'Natural Sciences', 'Social Sciences', 'Technology', 'Life Orientation',
        'Economic Management Sciences', 'Physical Sciences', 'Geography',
        'History', 'Business Studies', 'Accounting'
    ],

    phases: ['Foundation', 'Intermediate', 'Senior', 'FET'],

    // ── Agent-specific ─────────────────────────────────────
    genders: { male: 'M', female: 'F' },

    races: ['African', 'Coloured', 'White', 'Indian'],

    // ── Learner-specific ───────────────────────────────────
    learnerGenders: ['Male', 'Female'],
    learnerRaces: ['Black', 'White', 'Coloured', 'Indian'],
    assessmentStatuses: ['Assessed', 'Not Assessed'],
    employmentStatuses: ['1', '0'],  // 1=Employed, 0=Unemployed
    disabilityStatuses: ['0', '1'],  // 0=Not Disabled, 1=Has Disability

    // ── Schedule patterns ──────────────────────────────────
    schedulePatterns: ['weekly', 'biweekly', 'monthly'],
    weekdays: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
};
