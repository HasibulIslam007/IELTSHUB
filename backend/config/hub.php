<?php

return ['frontend_dev' => (bool) env('HUB_FRONTEND_DEV', false), 'mail_enabled' => (bool) env('MAIL_ENABLED', false), 'recording_retention_days' => (int) env('RECORDING_RETENTION_DAYS', 90), 'payment_provider' => null, 'ai_enabled' => false];
