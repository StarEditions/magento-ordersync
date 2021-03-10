<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Letsprintondemand\OrderSync\Api;

interface OrderupdateManagementInterface
{

    /**
     * POST for orderupdate api
     * @param string $param
     * @return string
     */
    public function postOrderupdate();
}

