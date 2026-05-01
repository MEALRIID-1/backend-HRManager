<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'HRManager')</title>
    <style>
        /* Reset */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        /* Container */
        .email-wrapper {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Header */
        .email-header {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            padding: 30px 20px;
            text-align: center;
        }
        
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .email-header .logo {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
            background-color: #ffffff;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }
        
        /* Body */
        .email-body {
            background-color: #ffffff;
            padding: 40px 30px;
            color: #374151;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .email-body h2 {
            color: #1F2937;
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .email-body h3 {
            color: #2563EB;
            font-size: 18px;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        
        .email-body p {
            margin-bottom: 16px;
        }
        
        /* Info Box */
        .info-box {
            background-color: #EFF6FF;
            border-left: 4px solid #2563EB;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .info-box strong {
            color: #1D4ED8;
        }
        
        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .data-table th {
            background-color: #F3F4F6;
            text-align: left;
            padding: 12px;
            font-weight: 600;
            color: #4B5563;
            border-bottom: 2px solid #E5E7EB;
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #E5E7EB;
        }
        
        /* Button */
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            text-align: center;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #1D4ED8 0%, #1E40AF 100%);
        }
        
        /* Status badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .badge-success {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .badge-danger {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        
        .badge-warning {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        /* Alert Box */
        .alert {
            padding: 16px;
            border-radius: 6px;
            margin: 20px 0;
        }
        
        .alert-info {
            background-color: #EFF6FF;
            border: 1px solid #BFDBFE;
            color: #1E40AF;
        }
        
        .alert-warning {
            background-color: #FFFBEB;
            border: 1px solid #FDE68A;
            color: #92400E;
        }
        
        /* Footer */
        .email-footer {
            background-color: #F3F4F6;
            padding: 30px;
            text-align: center;
            color: #6B7280;
            font-size: 14px;
        }
        
        .email-footer a {
            color: #2563EB;
            text-decoration: none;
        }
        
        .email-footer .social-links {
            margin-top: 20px;
        }
        
        .email-footer .social-links a {
            margin: 0 10px;
            color: #6B7280;
        }
        
        /* Responsive */
        @media screen and (max-width: 600px) {
            .email-body {
                padding: 30px 20px;
            }
            
            .email-header h1 {
                font-size: 20px;
            }
            
            .btn {
                display: block;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td>
                <div class="email-wrapper">
                    <!-- Header -->
                    <div class="email-header">
                        <div class="logo">👔</div>
                        <h1>HRManager</h1>
                    </div>
                    
                    <!-- Body -->
                    <div class="email-body">
                        @yield('content')
                    </div>
                    
                    <!-- Footer -->
                    <div class="email-footer">
                        <p>
                            <strong>HRManager</strong> - Système de Gestion des Ressources Humaines<br>
                            <a href="{{ config('app.frontend_url') }}">Accéder à l'application</a>
                        </p>
                        <p style="font-size: 12px; color: #9CA3AF; margin-top: 20px;">
                            Cet email a été envoyé automatiquement. Merci de ne pas y répondre.<br>
                            © {{ date('Y') }} HRManager. Tous droits réservés.
                        </p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
