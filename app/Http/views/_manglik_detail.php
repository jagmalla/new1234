<?php
/**
 * मंगल दोष (Manglik) DETAIL — shown at the top of the योग (Kundali Yoga)
 * prediction. Uses the SAME calculation as Kundali Milan
 * (GunaMilan::mangalPerson via CalcController::manglik). Scope: $view, $chart,
 * $h, $pcolor, $rashiHi.
 */
$mng = $view['manglik'] ?? null;
if ($mng !== null):
    $signs = \AutoBusiness\Astro\Calc\Charts::SIGNS;
    $marsSignIdx = (int) ($mng['mars_sign_index'] ?? 0);
    $marsSignHi = $rashiHi[$signs[$marsSignIdx] ?? ''] ?? ($signs[$marsSignIdx] ?? '');
    $marsHouse = (int) ($mng['mars_house_lagna'] ?? 0);
    $cancelBits = [];
    if (!empty($mng['cancel']['own_or_exalt'])) { $cancelBits[] = 'मंगल स्वराशि/उच्च राशि में'; }
    if (!empty($mng['cancel']['jupiter_or_lagna'])) { $cancelBits[] = 'गुरु की दृष्टि मंगल पर / लग्न में गुरु-शुक्र'; }
    if (!empty($mng['manglik'])) { $tone = 'neg'; $badge = 'मांगलिक'; }
    elseif (!empty($mng['partial'])) { $tone = 'mix'; $badge = 'गैर-मांगलिक (दोष-भंग)'; }
    else { $tone = 'pos'; $badge = 'गैर-मांगलिक'; }
?>
<div class="gen-card gen-c-<?= $tone ?>" style="margin-bottom:12px">
    <div class="gen-h">🔴 मंगल दोष (मांगलिक) विचार — <?= $h($badge) ?></div>
    <div class="gen-line"><b>मंगल की स्थिति:</b> <b style="color:<?= $pcolor('Mars') ?>"><?= $h($marsSignHi) ?></b> राशि · लग्न से भाव <b><?= $marsHouse ?></b>।</div>
    <?php if (!empty($mng['raw'])): ?>
    <div class="gen-line"><b>दोष-भाव में मंगल:</b> <?= $h(implode(', ', $mng['hits'])) ?>।</div>
        <?php if ($cancelBits !== []): ?>
        <div class="gen-line gen-pos"><b>दोष-भंग (परिहार):</b> <?= $h(implode(' · ', $cancelBits)) ?>। इस कारण मंगल दोष का प्रभाव बहुत कम / लगभग निष्प्रभावी।</div>
        <?php else: ?>
        <div class="gen-line gen-neg"><b>परिणाम:</b> मंगल दोष सक्रिय — विशेषकर विवाह व दाम्पत्य-जीवन में विचारणीय।</div>
        <?php endif; ?>
    <?php else: ?>
    <div class="gen-line gen-pos">मंगल किसी दोष-भाव (1·2·4·7·8·12) में नहीं — मंगल दोष नहीं।</div>
    <?php endif; ?>

    <div class="gen-line" style="margin-top:6px;color:#4b4640">
        <b>मंगल दोष क्या है?</b> जब मंगल जन्म-कुंडली में <b>लग्न, चन्द्र (या शुक्र)</b> से
        <b>1, 2, 4, 7, 8 या 12वें</b> भाव में हो, तो कुंडली "मांगलिक (मंगल दोष)" कहलाती है।
        इसका मुख्य प्रभाव विवाह में देरी, दाम्पत्य-मतभेद या स्वभाव में उग्रता माना जाता है।
    </div>
    <div class="gen-line" style="color:#4b4640">
        <b>दोष-भंग (परिहार):</b> मंगल का स्वराशि (मेष/वृश्चिक) या उच्च (मकर) में होना, गुरु की
        मंगल पर दृष्टि, अथवा लग्न में गुरु/शुक्र होने पर यह दोष कट जाता है। मिलान में दोनों
        पत्रिकाएँ मांगलिक हों तो भी दोष परस्पर कट जाता है।
    </div>
    <div class="gen-line" style="color:#4b4640">
        <b>उपाय:</b> मंगलवार व्रत, हनुमान उपासना/हनुमान चालीसा, मंगल-शान्ति व उपयुक्त
        मांगलिक-मिलान — पारम्परिक रूप से सुझाए जाते हैं।
    </div>
    <div class="gen-sub" style="margin-top:4px">यह गणना कुण्डली-मिलान (Kundali Milan) की मंगल-जाँच के समान है।</div>
</div>
<?php endif; ?>
