<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Client;

use LBHurtado\DeadDrop\TxtcmdrClient\DTO\ScheduleSmsRequest;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsRequest;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsResponse;

/**
 * Contract for txtcmdr API client
 */
interface TxtcmdrClientInterface
{
    /**
     * Send SMS immediately
     */
    public function send(SendSmsRequest $request): SendSmsResponse;

    /**
     * Schedule SMS for later
     */
    public function schedule(ScheduleSmsRequest $request): SendSmsResponse;
}
