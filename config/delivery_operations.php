<?php

return [
    'queue_connection' => env('DELIVERY_QUEUE_CONNECTION', 'database'),
    'queue_name' => env('DELIVERY_QUEUE_NAME', 'sales-document-delivery'),
    'heartbeat_interval_seconds' => (int) env('DELIVERY_HEARTBEAT_INTERVAL', 20),
    'heartbeat_stale_seconds' => (int) env('DELIVERY_HEARTBEAT_STALE', 75),
    'processing_lease_seconds' => (int) env('DELIVERY_PROCESSING_LEASE', 300),
];
