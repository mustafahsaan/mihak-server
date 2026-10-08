<?php

namespace App\Support;

use DomainException;

/**
 * خطأ في قاعدة عمل (رصيد غير كافٍ، مقاعد ممتلئة...). رسالته تُعرض للمستخدم كما هي:
 * في API كاستجابة 422، وفي الويب كرسالة خطأ في الصفحة السابقة.
 */
class RuleViolation extends DomainException {}
