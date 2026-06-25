<?php
use Illuminate\Support\Facades\Route;
use App\Webhooks\Skorozvon\SkorozvonWebhookConfig;

Route::redirect('/', '/admin')->name('home');

Route::webhooks(SkorozvonWebhookConfig::name());
