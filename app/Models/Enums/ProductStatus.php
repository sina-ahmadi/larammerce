<?php
/**
 * Created by PhpStorm.
 * User: a.morteza
 * Date: 2/20/19
 * Time: 2:49 PM
 */

namespace App\Models\Enums;

use App\Utils\Common\BaseEnum;

class ProductStatus extends BaseEnum
{
    const Disabled = 0;
    const Enabled = 1;
}