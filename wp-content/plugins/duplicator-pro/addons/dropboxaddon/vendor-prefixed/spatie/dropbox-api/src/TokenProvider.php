<?php

namespace VendorDuplicator\Spatie\Dropbox;

interface TokenProvider
{
    public function getToken(): string;
}
