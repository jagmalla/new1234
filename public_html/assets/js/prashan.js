/* ============================================================================
 * प्रश्न कुंडली (Prashan / Tajik-Neelkanthi Horary) — prediction engine
 *
 * Reads the question-moment chart (the gochar/transit chart cast for the
 * chosen date·time·place) and produces the GENERAL reading that applies to
 * every question: a strength gauge + लग्न, लग्न-स्थित ग्रह, लग्नेश, चन्द्र, and
 * all twelve भाव. Later, specific question-types plug into the same engine.
 *
 * Rules are from Tajik Neelkanthi (उदय-प्रकार, इत्थशाल/नक्त/मुसरिफ, चन्द्र-बल,
 * दोष, शुभ/क्रूर/पाप दृष्टि). Houses are counted from the PRASHNA lagna.
 *
 * Usage:  ABPrashan.render(hostEl, window.AB_GOCHAR, window.AB_GOCHAR_META)
 *
 * To add a rule later, extend the relevant data table below — the render
 * functions pick it up automatically.
 * ========================================================================== */
window.ABPrashan = (function () {
  'use strict';

  var SIGN_HI = ['मेष','वृषभ','मिथुन','कर्क','सिंह','कन्या','तुला','वृश्चिक','धनु','मकर','कुंभ','मीन'];
  var PL = { Sun:'सूर्य', Moon:'चन्द्र', Mars:'मंगल', Mercury:'बुध', Jupiter:'गुरु', Venus:'शुक्र', Saturn:'शनि', Rahu:'राहु', Ketu:'केतु' };

  // सीधे sign-index → स्वामी ग्रह
  var LORD = ['Mars','Venus','Mercury','Moon','Sun','Mercury','Venus','Mars','Jupiter','Saturn','Saturn','Jupiter'];
  // उच्च / नीच राशि (sign-index)
  var EX = { Sun:0, Moon:1, Mars:9, Mercury:5, Jupiter:3, Venus:11, Saturn:6 };
  var DE = { Sun:6, Moon:7, Mars:3, Mercury:11, Jupiter:9, Venus:5, Saturn:0 };
  var OWN = { Sun:[4], Moon:[3], Mars:[0,7], Mercury:[2,5], Jupiter:[8,11], Venus:[1,6], Saturn:[9,10] };
  // मित्र राशियाँ (स्वामी-मैत्री से) — मोटे तौर पर
  var FRIEND = {
    Sun:[3,0,7,8,11], Moon:[2,4,5,7,11], Mars:[3,4,8,11], Mercury:[1,4,6],
    Jupiter:[0,3,4,7], Venus:[2,5,9,10], Saturn:[1,2,5,6]
  };
  // दीप्तांश (orb) — इत्थशाल हेतु
  var ORB = { Sun:15, Moon:12, Mars:8, Mercury:7, Jupiter:9, Venus:7, Saturn:9, Rahu:9, Ketu:9 };
  // गति-क्रम (मंद→तीव्र): छोटा=मंद, बड़ा=तीव्र
  var SPEED = { Saturn:1, Jupiter:2, Mars:3, Sun:4, Venus:5, Mercury:6, Moon:7, Rahu:3, Ketu:3 };
  // मूल स्वभाव-श्रेणी
  var CAT = { Jupiter:'shubh', Venus:'shubh', Mercury:'shubh', Sun:'krur', Mars:'krur', Saturn:'paap', Rahu:'paap', Ketu:'paap' };

  // ── राशि तालिका (Sheet 1) — स्वभाव(चर/स्थिर/द्वि) · उदय · संयुक्त फल ──
  var N_CHARA='chara', N_STHIRA='sthira', N_DVI='dvi';
  var R_SHIR='shirsho', R_PRISH='prishtho', R_UBHAY='ubhay';
  var SIGN = [
    { nat:N_CHARA,  rise:R_PRISH, v:'स्थिति में बदलाव तो होगा, किंतु जल्दबाजी या शुरुआती संघर्ष के बाद ही काम बनेगा।' },
    { nat:N_STHIRA, rise:R_PRISH, v:'शुरुआती अड़चन व धीमी गति, किंतु एक बार काम बनने पर परिणाम अत्यंत स्थायी होगा।' },
    { nat:N_DVI,    rise:R_SHIR,  v:'बौद्धिक प्रयास या संवाद से काम सुगमता से बनेगा, हालांकि मन में दो विकल्प बने रहेंगे।' },
    { nat:N_CHARA,  rise:R_PRISH, v:'स्थान परिवर्तन या भावनात्मक उतार-चढ़ाव; शुरुआती चिंता के बाद स्थिति बदलेगी।' },
    { nat:N_STHIRA, rise:R_SHIR,  v:'उत्तम स्थिति: कार्य में शीघ्र सफलता और जो लाभ/पद मिलेगा वह स्थायी रहेगा।' },
    { nat:N_DVI,    rise:R_SHIR,  v:'व्यापार व समझौते में शीघ्र सफलता; थोड़ी दुविधा के बावजूद परिणाम पक्ष में।' },
    { nat:N_CHARA,  rise:R_SHIR,  v:'शीघ्रतम सिद्धि: यात्रा, व्यापार और परिवर्तन से जुड़े प्रश्नों में तुरंत सकारात्मक परिणाम।' },
    { nat:N_STHIRA, rise:R_SHIR,  v:'काम बनेगा, पर स्थिर व गोपनीय स्वभाव के कारण परिणाम अचानक सामने आएगा।' },
    { nat:N_DVI,    rise:R_PRISH, v:'शुरुआत में रुकावट और दोहरी स्थिति; शुभ ग्रह की दृष्टि से उत्तरार्ध में सफलता।' },
    { nat:N_CHARA,  rise:R_PRISH, v:'परिवर्तन निश्चित है, परंतु कठोर श्रम और विलंब के बाद ही लक्ष्य सिद्ध होगा।' },
    { nat:N_STHIRA, rise:R_SHIR,  v:'स्थायी सिद्धि: मित्रों/सहयोगियों से कार्य सुगमता से पूर्ण और लाभ दीर्घकालिक।' },
    { nat:N_DVI,    rise:R_UBHAY, v:'गुरु व शुभ ग्रहों के बलाबल अनुसार मध्यम गति से कार्य की पूर्ति।' }
  ];
  var NAT_HI  = { chara:'चर', sthira:'स्थिर', dvi:'द्विस्वभाव' };
  var NAT_SANKET = {
    chara:'वर्तमान स्थिति में शीघ्र बदलाव होगा।',
    sthira:'जो जैसा है, वैसा ही बना रहेगा (Status Quo)।',
    dvi:'प्रारंभ में दुविधा, बाद में परिस्थिति अनुसार बदलाव।'
  };
  var RISE_HI = { shirsho:'शीर्षोदय', prishtho:'पृष्ठोदय', ubhay:'उभयोदय' };
  var RISE_NOTE = {
    shirsho:'शुभ व कार्य-सिद्धिदायक — मनोरथ कम प्रयास में शीघ्र पूर्ण।',
    prishtho:'प्रारंभिक अड़चन/विलंब — अधिक परिश्रम के बाद काम बनता है।',
    ubhay:'मिश्रित — ग्रह-बलाबल अनुसार मध्यम गति से कार्य पूर्ति।'
  };

  // ── लग्न में ग्रह (Sheet 5) — मार्गी/वक्री/अस्त ──
  var IN_LAGNA = {
    Sun:{ dig:'मध्यम', direct:'संघर्ष/दबाव — ताप व अहंकार से बाधा; पर सरकारी/उच्च पद और मेष-सिंह लग्न में सफलता।', retro:'(सूर्य कभी वक्री नहीं होता)', combust:'(सूर्य स्वयं अस्त नहीं होता)' },
    Moon:{ dig:'शुभ (चतुर्थ दिग्बली)', direct:'पक्षबली-शुभ हो तो शीघ्र व शांत सिद्धि; क्षीण/नीच हो तो मानसिक चिंता व अटकाव।', retro:'(चंद्र कभी वक्री नहीं होता)', combust:'निष्फल — अमावस्या दोष से कुंडली का "प्राण" बलहीन; जातक निराश, कार्य असफल।' },
    Mars:{ dig:'मध्यम', direct:'विवाद/उग्रता — जल्दबाजी-झगड़े से कठिनाई; मेष/वृश्चिक/मकर लग्न या साहस के प्रश्न में सिद्धि।', retro:'उल्टा कदम/रुकावट — क्रोध/जिद में निर्णय बदलना; पुराने विवाद का उभरना।', combust:'पराक्रम भंग — ऊर्जा सूर्य-ताप में भस्म; उच्च दबाव के आगे विवशता व असफलता।' },
    Mercury:{ dig:'पूर्ण दिग्बली', direct:'उत्तम सिद्धि — व्यापार/बुद्धि/संवाद/बैंकिंग/दस्तावेज़ से काम शीघ्र बनेगा।', retro:'संशोधन व देरी — दो विकल्प, कागजी गलती; एक बार अटककर संशोधन के बाद बनेगा।', combust:'बौद्धिक अवरोध — बात सही न रख पाना, कागजी/तकनीकी चूक या दबाव से रुकना।' },
    Jupiter:{ dig:'सर्वोच्च दिग्बली (दोष-नाशक)', direct:'100% निर्विघ्न सिद्धि — हर प्रश्न (धन/पद/विवाह/स्वास्थ्य/मुकदमा) में निश्चित विजय व सम्मान।', retro:'पुनर्प्रयास से सिद्धि — नीति बदलने/देरी या दूसरे प्रयास में बनेगा; रुके धन की वापसी में शुभ।', combust:'सहयोग भंग — रक्षक बलहीन; बड़ों/अधिकारियों की मदद न मिलने से सुलझा काम भी अटकना।' },
    Venus:{ dig:'शुभ (चतुर्थ दिग्बली)', direct:'सुखद सिद्धि — विवाह/प्रेम/व्यापार/साझेदारी/वाहन/आर्थिक लाभ में त्वरित व शांत सफलता।', retro:'शर्तों में बदलाव/वापसी — नए सौदे में असमंजस; पर रूठे साथी के लौटने/अटके पैसे के प्रश्न में शुभ।', combust:'समझौता भंग — विवाह/साझेदारी अंतिम समय पर टूटना; लाभ केवल बाहरी दिखावा।' },
    Saturn:{ dig:'दिग्बलहीन', direct:'भारी विघ्न व देरी — रुकावट/बीमारी/थकान/हानि; केवल तुला/मकर/कुंभ लग्न में धैर्य से स्थायी फल।', retro:'दोहरा अटकाव — काम अति-धीमा, ठंडे बस्ते में; पुरानी गलती/कर्ज उभरना व घोर निराशा।', combust:'पूर्ण कार्य-नाश — सूर्य-शनि शत्रुता + अस्त दोष से हर तरफ निराशा, दंड या अपमान।' },
    Rahu:{ dig:'छाया ग्रह', direct:'भ्रम व धोखा — शंका/झूठे वादे/छिपे जोखिम से बाधा; मेष-वृषभ-मिथुन-कर्क-कन्या में शुभ दृष्ट हो तभी आकस्मिक लाभ।', retro:'भ्रम व धोखा — शंका/झूठे वादे/छिपे जोखिम से बाधा।', combust:'(सूर्य/चंद्र के साथ ग्रहण दोष बनाकर कार्य बिगाड़ता है)' },
    Ketu:{ dig:'छाया ग्रह', direct:'कार्य विच्छेद — आत्मविश्वास की कमी, घबराहट व स्वयं की चूक से अंतिम समय पर काम का कटना।', retro:'कार्य विच्छेद — घबराहट व स्वयं की चूक से अंतिम समय पर काम का कटना।', combust:'(ध्वज भंग / मनोबल विच्छेद कर कार्य रोकता है)' }
  };
  // लग्न में सूर्य/चन्द्र की विशेष युतियाँ (Sheet 5)
  var SUN_CONJ = {
    Rahu:'सूर्य+राहु (ग्रहण दोष) — सरकार/उच्चाधिकारी से झूठा आश्वासन, कलंक या षड्यंत्र; काम कानूनी-कागजी पेच में फंसेगा (नहीं)।',
    Ketu:'सूर्य+केतु (ध्वज भंग) — प्रतिष्ठा/पद के प्रश्न में निराशा; काम 90% पहुंचकर अचानक रुकना (नहीं)।',
    Saturn:'सूर्य+शनि (प्रकाश-अंधकार शत्रुता) — सरकारी दंड, पिता/अधिकारी से मतभेद, मुकदमे में उलझन (नहीं)।',
    Mars:'सूर्य+मंगल (द्वि-अग्नि) — उग्र विवाद/जल्दबाजी से हानि व रोग में रक्तचाप/चोट/सर्जरी; पर सेना/पुलिस/भूमि/साहस के प्रश्न में पराक्रम से विजय।'
  };
  var MOON_CONJ = {
    Sun:'चन्द्र+सूर्य (अमावस्या/मौढ्य दोष) — "प्राण" अस्त; कार्य केवल सोच बनकर रह जाता है (नहीं)।',
    Rahu:'चन्द्र+राहु (मानसिक भ्रम) — तीसरे व्यक्ति द्वारा धोखा या गुप्त हेरफेर की प्रबल आशंका (नहीं)।',
    Ketu:'चन्द्र+केतु (मनोबल विच्छेद) — घबराहट में गलत निर्णय या कार्य का त्याग (नहीं)।',
    Saturn:'चन्द्र+शनि (विष/पुनर्फू दोष) — कदम-कदम बाधा; हर बार काम बनता दिखेगा और अंतिम समय नई रुकावट (नहीं)।'
  };

  // ── बारह भाव — किन प्रश्नों के लिए (Prashna significations) ──
  var HOUSE_SIG = [
    'स्वयं · शरीर · स्वास्थ्य · सामान्य सफलता · प्रश्न का समग्र फल',
    'धन · कुटुंब · वाणी · संचित · भोजन · आँख',
    'पराक्रम · भाई-बहन · साहस · छोटी यात्रा · संपर्क · लेखन',
    'घर · भूमि · वाहन · माता · सुख · शिक्षा-स्थान',
    'संतान · विद्या · प्रेम · बुद्धि · सट्टा/निवेश',
    'रोग · ऋण · शत्रु · मुकदमा · नौकरी-प्रतियोगिता · सेवा',
    'विवाह · जीवनसाथी · साझेदारी · व्यापारिक सौदा',
    'बाधा · आयु · गुप्त · आकस्मिक घटना · रुका धन · बीमा',
    'भाग्य · पिता · धर्म · गुरु · लंबी यात्रा · उच्च शिक्षा',
    'कर्म · पद · नौकरी · व्यवसाय · प्रतिष्ठा · सरकार',
    'लाभ · आय · इच्छा-पूर्ति · मित्र · बड़े भाई',
    'व्यय · हानि · विदेश · मोक्ष · शय्या-सुख · अस्पताल'
  ];

  // ───────────────────────── गणना सहायक ─────────────────────────
  function houseOf(signIdx, asc) { return ((signIdx - asc + 12) % 12) + 1; }
  function signDist(a, b) { return ((b - a + 12) % 12) + 1; }   // a→b, 1..12
  function dignity(k, sIdx) {
    if (EX[k] === sIdx) return { label:'उच्च', score:100 };
    if (OWN[k] && OWN[k].indexOf(sIdx) >= 0) return { label:'स्वराशि', score:85 };
    if (DE[k] === sIdx) return { label:'नीच', score:20 };
    if (FRIEND[k] && FRIEND[k].indexOf(sIdx) >= 0) return { label:'मित्र राशि', score:65 };
    return { label:'सम', score:48 };
  }
  // दो ग्रहों में ताजिक दृष्टि-प्रकार (sign-distance से)
  function aspectType(dist) {
    if (dist === 1) return 'yuti';
    if (dist === 3 || dist === 5 || dist === 9 || dist === 11) return 'mitra';
    if (dist === 4 || dist === 7 || dist === 10) return 'shatru';
    return 'none';
  }
  // इत्थशाल / मुसरिफ — दीप्तांश व गति-क्रम से (सरलीकृत)
  // a,b = planet objects {key, sign_index, deg_in_sign}
  function itthasala(a, b) {
    if (!a || !b) return { type:'none' };
    var dist = signDist(a.sign_index, b.sign_index);
    var asp = aspectType(dist);
    if (asp === 'none') return { type:'none' };
    var fast = SPEED[a.key] >= SPEED[b.key] ? a : b;   // तीव्र ग्रह
    var slow = fast === a ? b : a;
    var orb = (ORB[a.key] + ORB[b.key]) / 2;
    var gap = Math.abs((a.deg_in_sign || 0) - (b.deg_in_sign || 0));
    if (gap > orb) return { type:'none', asp:asp };
    // तीव्र ग्रह कम अंश पर → पूर्ण (बनते) इत्थशाल; अधिक अंश पर → मुसरिफ (छूटते)
    if ((fast.deg_in_sign || 0) <= (slow.deg_in_sign || 0)) return { type:'purna', asp:asp, fast:fast.key, slow:slow.key };
    return { type:'musarif', asp:asp, fast:fast.key, slow:slow.key };
  }

  function clamp(n){ return Math.max(0, Math.min(100, Math.round(n))); }

  // चन्द्र-बल (Sheet 2)
  function moonStrength(ctx) {
    var mo = ctx.T.Moon || {}, notes = [], s = 50;
    // पक्ष-बल: पूर्णिमा के पास बली, अमावस्या के पास क्षीण (0..180 elong)
    var pk = ctx.elong <= 180 ? ctx.elong : 360 - ctx.elong;   // 0..180
    var pakshaScore = pk / 180 * 100;                           // 0=अमावस्या, 100=पूर्णिमा
    if (ctx.waxing) notes.push('शुक्ल पक्ष — बढ़ता चन्द्र'); else notes.push('कृष्ण पक्ष — घटता चन्द्र');
    // राशि-बल
    var dg = dignity('Moon', mo.sign_index);
    // भाव/दिग्बल
    var h = houseOf(mo.sign_index, ctx.asc);
    var houseScore = (h===4)?100 : (h===1||h===7||h===10)?80 : (h===5||h===9||h===11)?75 : (h===3||h===2)?55 : (h===8)?15 : (h===6||h===12)?30 : 50;
    if (h===4) notes.push('चतुर्थ में पूर्ण दिग्बली');
    else if (h===8) notes.push('अष्टम का चन्द्र — प्रश्न में मृत्यु-तुल्य बाधाकारक');
    else if (h===6||h===12) notes.push('त्रिक (6/8/12) में — सपोर्ट का अभाव');
    else if (h===1||h===4||h===7||h===10||h===5||h===9||h===11) notes.push('केंद्र/त्रिकोण/लाभ में — शुभ');
    if (mo.combust) { notes.push('सूर्य से अस्त (अमावस्या दोष) — प्राण बलहीन'); s -= 40; }
    s = pakshaScore*0.4 + dg.score*0.3 + houseScore*0.3;
    if (mo.combust) s = Math.min(s, 22);
    notes.unshift('राशि-बल: ' + dg.label + ' (' + SIGN_HI[mo.sign_index] + ')');
    return { score: clamp(s), notes: notes };
  }

  // ───────── सन्दर्भ (context) — सब भाव प्रश्न-लग्न से ─────────
  function buildCtx(g) {
    var asc = g.ascendant.sign_index, T = {};
    Object.keys(g.transits).forEach(function (n) {
      var t = g.transits[n];
      T[n] = { key:n, sign_index:t.sign_index, deg_in_sign:(t.deg_in_sign!=null?t.deg_in_sign:(t.deg||0)),
               retro:!!t.retro, combust:!!t.combust, nak:t.nakshatra?t.nakshatra.name:'' };
    });
    var sun = T.Sun || {}, moon = T.Moon || {};
    var elong = ((((moon.deg_in_sign!=null? (moon.sign_index*30+moon.deg_in_sign):0) -
                   (sun.deg_in_sign!=null? (sun.sign_index*30+sun.deg_in_sign):0)) % 360) + 360) % 360;
    return { asc:asc, T:T, llKey:LORD[asc], elong:elong, waxing: elong<=180 };
  }

  // ─────────────────── बल-गणना (5 कारक) ───────────────────
  function strengths(ctx) {
    var asc = ctx.asc, T = ctx.T, moon = T.Moon || {};
    var sg = SIGN[asc];
    // 1) लग्न बल — उदय-प्रकार + लग्न में शुभ/पाप
    var lagna = sg.rise===R_SHIR?78 : sg.rise===R_UBHAY?55 : 40;
    Object.keys(T).forEach(function(k){
      if (houseOf(T[k].sign_index, asc)===1) {
        lagna += CAT[k]==='shubh'?12 : CAT[k]==='krur'?-8 : -12;
      }
    });
    // 2) लग्नेश बल — दिग्/राशि-बल + भाव + अस्त/वक्री
    var ll = T[ctx.llKey]; var llScore=48;
    if (ll) {
      llScore = dignity(ctx.llKey, ll.sign_index).score;
      var lh = houseOf(ll.sign_index, asc);
      llScore += (lh===1||lh===4||lh===7||lh===10||lh===5||lh===9||lh===11)?8 : (lh===6||lh===8||lh===12)?-15:0;
      if (ll.combust) llScore -= 25;
      if (ll.retro)   llScore -= 8;
    }
    // 3) लग्न–चन्द्र — चन्द्र का भाव + इत्थशाल
    var mh = houseOf(moon.sign_index, asc);
    var lm = (mh===1)?92 : (mh===4||mh===7||mh===10)?80 : (mh===5||mh===9||mh===11)?74 : (mh===3||mh===2)?52 : (mh===8)?18 : (mh===6||mh===12)?30 : 50;
    // लग्न-बिंदु को लग्नेश-सम मानकर इत्थशाल (approx): चन्द्र का लग्नेश से
    // 4) लग्नेश–चन्द्र — दृष्टि/युति/इत्थशाल
    var lmoon = 50, llmoon = 50, ith = { type:'none' }, aspLM='none';
    if (ll) {
      aspLM = aspectType(signDist(ll.sign_index, moon.sign_index));
      ith = itthasala({key:ctx.llKey, sign_index:ll.sign_index, deg_in_sign:ll.deg_in_sign},
                      {key:'Moon', sign_index:moon.sign_index, deg_in_sign:moon.deg_in_sign});
      llmoon = aspLM==='mitra'?78 : aspLM==='yuti'?66 : aspLM==='shatru'?34 : 48;
      if (ith.type==='purna')   llmoon = Math.max(llmoon, 92);
      if (ith.type==='musarif') llmoon = Math.min(llmoon, 30);
    }
    // 5) चन्द्र बल
    var ms = moonStrength(ctx);
    var S = { lagna:clamp(lagna), lagnesh:clamp(llScore), lagnaMoon:clamp(lm),
              lagneshMoon:clamp(llmoon), moon:ms.score, moonNotes:ms.notes,
              aspLM:aspLM, ith:ith, moonHouse:mh };
    // समग्र कार्य-सिद्धि बल (भारित)
    S.overall = clamp(S.lagna*0.15 + S.lagnesh*0.25 + S.lagnaMoon*0.15 + S.lagneshMoon*0.2 + S.moon*0.25);
    return S;
  }

  function barColor(v){ return v>=67?['#15803d','#dcfce7'] : v>=45?['#b45309','#fef3c7'] : ['#b91c1c','#fee2e2']; }
  function barLabel(v){ return v>=67?'प्रबल' : v>=45?'मध्यम' : 'क्षीण'; }
  function bar(label, v, big) {
    var c = barColor(v), h = big?'14px':'9px', fs = big?'.98rem':'.8rem';
    return '<div style="margin:'+(big?'4px 0 10px':'5px 0')+'">'
      + '<div style="display:flex;justify-content:space-between;font-size:'+fs+';font-weight:'+(big?800:600)+';color:#334155">'
      +   '<span>'+label+'</span><span style="color:'+c[0]+'">'+barLabel(v)+' · '+v+'%</span></div>'
      + '<div style="background:'+c[1]+';border-radius:6px;height:'+h+';margin-top:3px;overflow:hidden">'
      +   '<div style="width:'+v+'%;height:100%;background:'+c[0]+';border-radius:6px"></div></div></div>';
  }

  function card(tone, html) {
    var col = tone==='shubh'?['#15803d','#ecfdf5','#bbf7d0'] : tone==='ashubh'?['#b91c1c','#fef2f2','#fecaca'] : ['#b45309','#fffbeb','#fde68a'];
    return '<div style="border:1px solid '+col[2]+';background:'+col[1]+';border-left:4px solid '+col[0]
      +';border-radius:8px;padding:7px 10px;margin:5px 0;font-size:.9rem;line-height:1.55">'+html+'</div>';
  }
  function head(txt){ return '<div style="font-weight:800;font-size:.96rem;color:#7c2d12;margin:14px 0 4px;border-bottom:2px solid #fde68a;padding-bottom:3px">'+txt+'</div>'; }

  // इत्थशाल/दृष्टि को वाक्य में
  function relPhrase(name, ll, other, keyA, keyB) {
    var a = aspectType(signDist(ll.sign_index, other.sign_index));
    var it = itthasala({key:keyA, sign_index:ll.sign_index, deg_in_sign:ll.deg_in_sign},
                       {key:keyB, sign_index:other.sign_index, deg_in_sign:other.deg_in_sign});
    if (it.type==='purna')   return { tone:'shubh',  t:'पूर्ण इत्थशाल — सीधा व सक्रिय तालमेल; '+name+' सिद्धि की ओर।' };
    if (it.type==='musarif') return { tone:'ashubh', t:'मुसरिफ (छूटता संबंध) — अवसर हाथ से निकल रहा है।' };
    if (a==='mitra')  return { tone:'shubh',  t:'मित्र दृष्टि (3/11 या 5/9) — '+name+' अनुकूल, काम बनेगा।' };
    if (a==='yuti')   return { tone:'note',   t:'युति (एक ही राशि) — निकट संबंध; शुभ-पाप बलाबल पर निर्भर।' };
    if (a==='shatru') return { tone:'ashubh', t:'शत्रु दृष्टि/युति (1/7 या 4/10) — बाधा/मतभेद का योग।' };
    return { tone:'note', t:'कोई सीधा संबंध नहीं (6/8/12) — इस समय दूरी बनी हुई है।' };
  }

  function renderGeneral(host, ctx, meta) {
    var asc = ctx.asc, T = ctx.T, moon = T.Moon || {}, ll = T[ctx.llKey];
    var sg = SIGN[asc];
    var when = meta ? [meta.date, meta.time, meta.place].filter(Boolean).join(' · ') : '';
    var S = strengths(ctx);
    var h = '';

    // शीर्ष + श्रेणी ड्रॉपडाउन
    h += '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:4px">'
      +   '<div style="font-weight:800;font-size:1.05rem;color:#7c2d12">🔮 प्रश्न कुंडली — फल</div>'
      +   '<label style="font-size:.78rem;color:#475569;font-weight:700">श्रेणी: '
      +     '<select id="prashan-cat" style="font-size:.82rem;font-weight:700;border:1.5px solid #c2410c;border-radius:6px;padding:3px 8px;background:#fff7ed;color:#7c2d12">'
      +       '<option value="general" selected>सामान्य (General)</option>'
      +     '</select></label>'
      + '</div>';
    if (when) h += '<div style="font-size:.76rem;color:#64748b;margin-bottom:8px">प्रश्न का क्षण: '+when+'</div>';

    // ── बल-सूचक ──
    h += '<div style="border:1px solid #e5e7eb;background:#f8fafc;border-radius:10px;padding:10px 12px;margin-bottom:8px">';
    h += bar('कार्य-सिद्धि बल (समग्र)', S.overall, true);
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px">'
      +   '<div>'+bar('लग्न बल', S.lagna)+bar('लग्नेश बल', S.lagnesh)+bar('चन्द्र बल', S.moon)+'</div>'
      +   '<div>'+bar('लग्न ↔ चन्द्र', S.lagnaMoon)+bar('लग्नेश ↔ चन्द्र', S.lagneshMoon)+'</div>'
      + '</div></div>';

    // आधार पंक्ति
    h += '<div style="border:1px solid #e5e7eb;background:#fff;border-radius:8px;padding:8px 10px;margin-bottom:6px;font-size:.86rem;line-height:1.7">'
      +   '<b>प्रश्न-लग्न:</b> '+SIGN_HI[asc]+' ('+NAT_HI[sg.nat]+' · '+RISE_HI[sg.rise]+') · स्वामी '+PL[ctx.llKey]+'<br>'
      +   '<b>चन्द्र:</b> '+SIGN_HI[moon.sign_index]+(moon.nak?' · नक्षत्र '+moon.nak:'')+' · '+(ctx.waxing?'शुक्ल पक्ष':'कृष्ण पक्ष')
      + '</div>';

    // ── A. लग्न फल (राशि अनुसार) ──
    h += head('① लग्न फल — '+SIGN_HI[asc]+' ('+NAT_HI[sg.nat]+')');
    h += card('note', '<b>मूल संकेत:</b> '+NAT_SANKET[sg.nat]);
    h += card(sg.rise===R_SHIR?'shubh':sg.rise===R_UBHAY?'note':'ashubh',
              '<b>'+RISE_HI[sg.rise]+':</b> '+RISE_NOTE[sg.rise]);
    h += card('note', '<b>संयुक्त फल:</b> '+sg.v);

    // ── B. लग्न में ग्रह ──
    var inL = Object.keys(T).filter(function(k){ return houseOf(T[k].sign_index, asc)===1; });
    h += head('② लग्न में ग्रह');
    if (!inL.length) {
      h += card('note', 'लग्न में कोई ग्रह नहीं — लग्नेश व चन्द्र की स्थिति (नीचे) प्रश्न का मुख्य आधार हैं।');
    } else {
      inL.forEach(function(k){
        var pl = T[k], d = IN_LAGNA[k], state = pl.combust?'combust':pl.retro?'retro':'direct';
        var cat = CAT[k]==='shubh'?'shubh':CAT[k]==='krur'?'note':'ashubh';
        var line = '<b>'+PL[k]+' लग्न में</b> ('+d.dig+') — '+ (d[state]||d.direct);
        h += card(cat, line);
        // विशेष युतियाँ
        inL.forEach(function(k2){
          if (k==='Sun' && SUN_CONJ[k2]) h += card('ashubh', SUN_CONJ[k2]);
          if (k==='Moon' && MOON_CONJ[k2]) h += card('ashubh', MOON_CONJ[k2]);
        });
      });
    }

    // ── C. लग्नेश की स्थिति ──
    h += head('③ लग्नेश ('+PL[ctx.llKey]+') की स्थिति');
    if (ll) {
      var lh = houseOf(ll.sign_index, asc), dg = dignity(ctx.llKey, ll.sign_index);
      var tone = dg.score>=65?'shubh':dg.score<=25?'ashubh':'note';
      h += card(tone, '<b>'+PL[ctx.llKey]+'</b> — '+SIGN_HI[ll.sign_index]+' ('+dg.label+') में, भाव '+lh+' ('+HOUSE_SIG[lh-1].split('·')[0].trim()+'…)।'
        + (lh===6||lh===8||lh===12?' दुःस्थान में — विघ्न/देरी का योग।':(lh===1||lh===4||lh===7||lh===10||lh===5||lh===9||lh===11?' केंद्र/त्रिकोण/लाभ में — प्रश्नकर्ता सक्षम।':'')));
      if (ll.retro)   h += card('note',   '<b>लग्नेश वक्री</b> — प्रश्नकर्ता निर्णय पर अडिग नहीं; पर रुके धन/खोई वस्तु/लौटने के प्रश्न में वापसी का शुभ संकेत।');
      if (ll.combust) h += card('ashubh', '<b>लग्नेश अस्त</b> — प्रश्नकर्ता शक्तिहीन/दबाव में; प्रयास करने में असमर्थ।');
      var r = relPhrase('प्रश्नकर्ता–मन', ll, moon, ctx.llKey, 'Moon');
      h += card(r.tone, '<b>लग्नेश ↔ चन्द्र:</b> '+r.t);
    }

    // ── D. चन्द्र की स्थिति व बल ──
    h += head('④ चन्द्र बल (कुंडली का "प्राण")');
    var mtone = S.moon>=67?'shubh':S.moon<=30?'ashubh':'note';
    h += card(mtone, '<b>चन्द्र बल '+S.moon+'% ('+barLabel(S.moon)+')</b> — '+S.moonNotes.join(' · ')+'।');
    h += card('note', S.moon>=67?'चन्द्र सक्षम है — इस समय मन/परिस्थिति कार्य को पूरा सहयोग दे रही है।'
                     : S.moon>=45?'चन्द्र मध्यम — सामान्य प्रयास व निर्धारित समय से काम बनेगा।'
                     : 'चन्द्र निर्बल — इच्छा तो है पर सामर्थ्य/सहयोग की कमी; काम अधूरा या विलंबित रह सकता है।');

    // ── E. बारह भाव फल ──
    h += head('⑤ बारह भाव — अपने प्रश्न का भाव देखें');
    h += '<div style="font-size:.76rem;color:#64748b;margin:2px 0 4px">प्रश्न जिस विषय का हो, उसी भाव की पंक्ति पढ़ें। हर भाव का "कार्येश" (भाव-स्वामी) देखकर सिद्धि आँकी गई है।</div>';
    for (var H=1; H<=12; H++) {
      var hsign = (asc + H - 1) % 12;
      var kKey = LORD[hsign], kPl = T[kKey];
      var occ = Object.keys(T).filter(function(k){ return houseOf(T[k].sign_index, asc)===H; });
      var nat = SIGN[hsign].nat;
      var verdict, vtone;
      if (kPl) {
        var rel = relPhrase('भाव-'+H, kPl, moon, kKey, 'Moon');       // कार्येश ↔ चन्द्र
        var kll = ll ? aspectType(signDist(ll.sign_index, kPl.sign_index)) : 'none';
        var kith = ll ? itthasala({key:ctx.llKey,sign_index:ll.sign_index,deg_in_sign:ll.deg_in_sign},
                                  {key:kKey,sign_index:kPl.sign_index,deg_in_sign:kPl.deg_in_sign}) : {type:'none'};
        var kdg = dignity(kKey, kPl.sign_index);
        var good = (rel.tone==='shubh') || kith.type==='purna' || kll==='mitra';
        var bad  = (rel.tone==='ashubh') || kith.type==='musarif' || kll==='shatru' || kdg.score<=25 || kPl.combust;
        vtone = good && !kPl.combust ? 'shubh' : bad ? 'ashubh' : 'note';
        verdict = 'कार्येश ' + PL[kKey] + ' ' + SIGN_HI[kPl.sign_index] + ' (' + kdg.label + '), भाव ' + houseOf(kPl.sign_index, asc) + '। '
          + (kith.type==='purna' ? 'लग्नेश से पूर्ण इत्थशाल — सिद्धि प्रबल।'
            : kith.type==='musarif' ? 'लग्नेश से मुसरिफ — अवसर निकल रहा है।'
            : kll==='mitra' ? 'लग्नेश से मित्र दृष्टि — अनुकूल।'
            : kll==='shatru' ? 'लग्नेश से शत्रु दृष्टि — बाधा।'
            : 'लग्नेश से सीधा संबंध नहीं।')
          + (kPl.combust ? ' कार्येश अस्त — रद्द योग।' : kPl.retro ? ' कार्येश वक्री — देरी/वापसी।' : '');
      } else { vtone='note'; verdict='कार्येश की गणना उपलब्ध नहीं।'; }
      var occTxt = occ.length ? ' <span style="color:#475569">· भाव में: '+occ.map(function(k){return PL[k]+(T[k].combust?'(अस्त)':T[k].retro?'(व)':'');}).join(', ')+'</span>' : '';
      var col = vtone==='shubh'?['#15803d','#f0fdf4'] : vtone==='ashubh'?['#b91c1c','#fef2f2'] : ['#b45309','#fffbeb'];
      h += '<div style="border:1px solid #eee;border-left:4px solid '+col[0]+';background:'+col[1]+';border-radius:7px;padding:6px 9px;margin:5px 0">'
        + '<div style="font-weight:800;font-size:.9rem;color:#1f2937">'+H+'. '+HOUSE_SIG[H-1]+'</div>'
        + '<div style="font-size:.82rem;color:#334155;margin-top:2px">राशि '+SIGN_HI[hsign]+' ('+NAT_HI[nat]+' — '+NAT_SANKET[nat]+')'+occTxt+'</div>'
        + '<div style="font-size:.86rem;color:'+col[0]+';font-weight:600;margin-top:3px">'+verdict+'</div>'
        + '</div>';
    }

    // पाद-टिप्पणी
    h += '<div style="font-size:.74rem;color:#94a3b8;margin-top:12px;border-top:1px dashed #e5e7eb;padding-top:6px">'
      + 'नोट: प्रश्न कुंडली उसी पल की है जब प्रश्न पूछा गया — ऊपर तिथि/समय/स्थान वही रखें। '
      + 'यह "सामान्य" फल है जो हर प्रश्न पर लागू होता है; आगे विशिष्ट प्रश्न-प्रकार श्रेणी-ड्रॉपडाउन से जुड़ेंगे।</div>';

    host.innerHTML = h;
  }

  return {
    render: function (host, g, meta) {
      if (!host || !g || !g.transits || !g.ascendant) return;
      renderGeneral(host, buildCtx(g), meta);
    }
  };
})();
