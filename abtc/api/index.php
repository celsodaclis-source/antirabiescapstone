<?php

/**
 * Vercel serverless entrypoint for Laravel.
 *
 * vercel-php executes this file per request. It simply boots the
 * standard Laravel public front-controller.
 */
require __DIR__.'/../public/index.php';
