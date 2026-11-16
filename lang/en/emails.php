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

    'circulation' => [
        'labels' => [
            'barcode' => 'Barcode',
            'inventory_number' => 'Inventory number',
            'library' => 'Library',
            'pickup_until' => 'Pick up by',
            'expires_at' => 'Reservation expires',
            'my_reservations' => 'My reservations',
            'library_reservations' => 'Library reservations',
            'issued_at' => 'Issued at',
            'due_at' => 'Due date',
            'due_soon' => 'Due soon',
            'due_today' => 'Due today',
            'returned_at' => 'Returned at',
            'overdue' => 'Overdue',
            'days' => 'days',
            'none' => 'No items.',
            'my_loans' => 'My loans',
        ],
        'reservation_created_user' => [
            'subject' => 'Reservation created',
            'intro' => 'Hello :name, your reservation has been recorded. The copy is held for you until the stated deadline.',
        ],
        'reservation_created_staff' => [
            'subject' => 'New reservation',
            'intro' => 'User :name created a new reservation.',
        ],
        'reservation_cancelled_user' => [
            'subject' => 'Reservation cancelled',
            'intro' => 'Your reservation has been cancelled.',
        ],
        'reservation_cancelled_staff' => [
            'subject' => 'User cancelled a reservation',
            'intro' => 'User :name cancelled an uncollected reservation.',
        ],
        'reservation_expiring_user' => [
            'subject' => 'Reservation expiring soon',
            'intro' => 'Your reservation expires in approximately :hours hours. Please collect the book in time.',
        ],
        'reservation_expired_user' => [
            'subject' => 'Reservation expired',
            'intro' => 'Your reservation has expired and the reserved copy is available again.',
        ],
        'loan_issued_user' => [
            'subject' => 'Book issued',
            'intro' => 'Hello :name, the book has been issued. The due date is shown below.',
        ],
        'loan_issued_staff' => [
            'subject' => 'Issue confirmation',
            'intro' => 'The book was issued to :name.',
        ],
        'loan_returned_user' => [
            'subject' => 'Book returned',
            'intro' => 'The book has been returned successfully.',
        ],
        'loan_returned_staff' => [
            'subject' => 'Return confirmation',
            'intro' => 'User :name returned the book.',
        ],
        'loan_due_soon_user' => [
            'subject' => 'Reminder: due date approaching',
            'intro' => 'The following books are due soon.',
        ],
        'loan_due_today_user' => [
            'subject' => 'Due today',
            'intro' => 'The following books are due today.',
        ],
        'loan_overdue_user' => [
            'subject' => 'Loan overdue',
            'intro' => 'The following books are overdue.',
        ],
        'staff_daily_digest' => [
            'subject' => 'Daily circulation digest',
            'intro' => 'Circulation overview for :library.',
            'due_title' => 'Books due in 3 days',
            'overdue_title' => 'Overdue loans',
            'reservations_title' => 'Expiring reservations',
        ],
    ],

    'brand' => [
        'name' => 'eBiblioteka',
        'tagline' => 'every book is a new adventure',
    ],

];
