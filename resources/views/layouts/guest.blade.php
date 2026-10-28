<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
         <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
         <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Scripts -->
        <style>
            /* Login page styling */
            .login-container {
                height: 100vh;
                width: 100vw;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            .background-image {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-image: url('{{ asset('/images/bgproduct.jpg') }}');
                background-size: cover;
                background-position: center;
                z-index: 0;
            }

            .login-form-overlay {
                background: rgba(255, 255, 255, 0.308);
                backdrop-filter: blur(10px);
                border-radius: 10px;
                padding: 30px;
                width: 100%;
                max-width: 400px;
                z-index: 1;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            }

            .user-icon-container {
                display: flex;
                justify-content: center;
                margin-bottom: 20px;
            }

            .user-icon-container i {
                font-size: 3rem;
                color: #304b8a;
                background: #f0f0f0;
                border-radius: 50%;
                width: 70px;
                height: 70px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-control {
                border-radius: 25px;
                padding: 12px 20px;
                border: 1px solid #ddd;
            }

            /* Invalid state: red border */
            .form-control.is-invalid {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 0.15rem rgba(220,53,69,0.1);
            }

            /* Shake animation for attention */
            @keyframes shake {
                0% { transform: translateX(0); }
                25% { transform: translateX(-6px); }
                50% { transform: translateX(6px); }
                75% { transform: translateX(-4px); }
                100% { transform: translateX(0); }
            }

            .shake {
                animation: shake 0.45s cubic-bezier(.36,.07,.19,.97);
            }

            .form-options {
                margin-bottom: 20px;
                font-size: 0.9rem;
            }

            .forgot-link {
                color: #304b8a;
                text-decoration: none;
            }

            .login-btn {
                width: 100%;
                border-radius: 25px;
                padding: 10px;
                background-color: #304b8a;
                border: none;
                font-weight: 600;
                margin-bottom: 20px;
            }

            .divider {
                text-align: center;
                margin: 20px 0;
                position: relative;
            }

            .divider::before,
            .divider::after {
                content: "";
                position: absolute;
                top: 50%;
                width: 40%;
                height: 1px;
                background-color: #ddd;
            }

            .divider::before {
                left: 0;
            }

            .divider::after {
                right: 0;
            }

            .divider span {
                display: inline-block;
                padding: 0 10px;
                background-color: #fff;
                position: relative;
                z-index: 1;
                color: #777;
                font-size: 0.9rem;
            }

            .social-login {
                display: flex;
                justify-content: center;
                gap: 15px;
            }

            .social-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 35px;
                height: 35px;
                border-radius: 50%;
                background-color: #f5f5f5;
                color: #304b8a;
                text-decoration: none;
                transition: all 0.3s ease;
            }

            .social-icon:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
            }
        </style>
        <!-- Styles -->

    </head>
    <body>
        <div class="font-sans text-gray-900 antialiased">
            {{ $slot }}
        </div>

       <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    </body>
</html>
