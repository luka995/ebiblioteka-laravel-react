<?php

return [

    'reset' => [
        'subject' => 'Reset your password',
        'greeting' => 'Hello!',
        'intro' => 'We received a request to reset the password for your eBiblioteka account.',
        'action' => 'Reset password',
        'outro' => 'If you did not request a password reset, you can safely ignore this email.',
        'subcopy_intro' => "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below into your web browser:",
        'expiry' => 'This link expires in :count minutes.',
    ],

    'account' => [
        'subject' => 'Your account has been created',
        'greeting' => 'Welcome!',
        'intro' => 'Your eBiblioteka account has been created. Below you will find your login details.',
        'email_label' => 'E-mail',
        'password_label' => 'Password',
        'action' => 'Sign in',
        'outro' => 'After signing in, we recommend changing your password in the account settings.',
    ],

    'password_changed' => [
        'subject' => 'Your password has been changed',
        'greeting' => 'Hello!',
        'intro' => 'An administrator has changed the password for your eBiblioteka account. Your new login details are below.',
        'email_label' => 'E-mail',
        'password_label' => 'New password',
        'action' => 'Sign in',
        'outro' => 'If you did not expect this change, please contact your library administrator immediately.',
    ],

    'contact' => [
        'subject' => 'New message from the contact form',
        'greeting' => 'New message from the contact form',
        'intro' => 'You have received a new message through the contact form on the eBiblioteka website.',
        'name_label' => 'Name',
        'email_label' => 'E-mail',
        'organization_label' => 'Institution or organization',
        'organization_none' => 'Not provided',
        'message_label' => 'Message',
    ],

    'library_request' => [
        'subject' => 'New library request — :school',
        'greeting' => 'New library request',
        'intro' => 'A new library request arrived through the website. The school details are below.',
        'school_label' => 'School',
        'type_label' => 'School type',
        'city_label' => 'City',
        'address_label' => 'Address',
        'contact_label' => 'Contact person',
        'email_label' => 'E-mail',
        'phone_label' => 'Phone',
        'students_label' => 'Number of students',
        'employees_label' => 'Number of employees',
        'message_label' => 'Message',
        'none' => 'Not provided',
    ],

    'brand' => [
        'name' => 'eBiblioteka',
        'tagline' => 'every book is a new adventure',
    ],

];
