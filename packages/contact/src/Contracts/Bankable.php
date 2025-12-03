<?php

namespace LBHurtado\Contact\Contracts;

interface Bankable
{
    public function getBankCode(): string;

    public function getAccountNumber(): string;
}
