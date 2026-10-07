@include('errors.layout', ['code' => '503', 'heading' => __('We are doing some maintenance'), 'text' => $message ?: __('The service will be back shortly. Thank you for your patience.')])
