<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de travail - HRManager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 20px;
            padding: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        td {
            vertical-align: top;
            padding: 4px;
        }
        
        /* Classes pour les bordures */
        .border-bottom {
            border-bottom: 2px solid #d1d5db;
        }
        
        .border-top {
            border-top: 2px solid #d1d5db;
        }
        
        .border-gray {
            border-color: #d1d5db;
        }
        
        /* Classes pour l'espacement */
        .pb-6 {
            padding-bottom: 15px;
        }
        
        .mb-6 {
            margin-bottom: 15px;
        }
        
        .mb-4 {
            margin-bottom: 10px;
        }
        
        .mb-2 {
            margin-bottom: 5px;
        }
        
        .mt-6 {
            margin-top: 15px;
        }
        
        .mt-8 {
            margin-top: 20px;
        }
        
        .pt-6 {
            padding-top: 15px;
        }
        
        .p-4 {
            padding: 10px;
        }
        
        /* Classes pour le texte */
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-sm {
            font-size: 9px;
        }
        
        .text-lg {
            font-size: 13px;
        }
        
        .text-2xl {
            font-size: 18px;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .font-semibold {
            font-weight: 600;
        }
        
        .font-medium {
            font-weight: 500;
        }
        
        /* Classes pour les couleurs */
        .text-gray-500 {
            color: #6b7280;
        }
        
        .text-gray-600 {
            color: #4b5563;
        }
        
        .text-gray-900 {
            color: #111827;
        }
        
        .bg-gray-50 {
            background-color: #f9fafb;
        }
        
        /* Classes pour les bordures arrondies */
        .rounded-lg {
            border-radius: 5px;
        }
        
        .border {
            border: 1px solid #e5e7eb;
        }
        
        /* Largeurs */
        .w-50 {
            width: 50%;
        }
        
        .w-45 {
            width: 45%;
        }
        
        .w-100 {
            width: 100%;
        }
        
        /* Espacement */
        .gap-4 {
            gap: 10px;
        }
        
        .gap-8 {
            gap: 20px;
        }
        
        .italic {
            font-style: italic;
        }
    </style>
</head>
<body>

<!-- En-tête -->
<table class="border-bottom pb-6 mb-6">
    <tr>
        <td class="text-center">
            <table style="width: auto; margin: 0 auto;">
                <tr>
                    <td>
                        <h1 class="text-2xl font-bold text-gray-900">HRManager</h1>
                        <p class="text-sm text-gray-500">Solutions de Gestion RH</p>
                    </td>
                </tr>
            </table>
            <div class="text-sm text-gray-600 text-center mt-2">
                123 Avenue des Technologies<br>
                75000 Douala-Cameroun<br>
                Tél: +33 1 23 45 67 89<br>
                Email: contact@hrmanager.com
            </div>
        </td>
    </tr>
</table>

<!-- Destinataire -->
<table class="mb-6">
    <tr>
        <td class="text-right">
            <table style="width: 45%; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 5px; margin-left: auto;">
                <tr>
                    <td style="padding: 10px;">
                        <p class="text-sm text-gray-500 mb-1">À l'attention de :</p>
                        <p class="text-lg font-semibold text-gray-900">M./Mme {{ $employe['prenom'] ?? '' }} {{ $employe['nom'] ?? '' }}</p>
                        <p class="text-sm text-gray-600">{{ $employe['email'] ?? 'Email non renseigné' }}</p>
                        <p class="text-sm text-gray-600">{{ $employe['departement'] ?? 'Département non spécifié' }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Objet -->
<table class="mb-6">
    <tr>
        <td>
            <p class="text-sm text-gray-500 mb-1">Objet :</p>
            @php
                $typeLabels = [
                    'cdi' => 'Contrat de travail à durée indéterminée',
                    'cdd' => 'Contrat de travail à durée déterminée',
                    'stage' => 'Convention de stage',
                    'alternance' => "Contrat d'alternance",
                    'freelance' => 'Contrat de prestation de services',
                ];
            @endphp
            <p class="text-lg font-semibold text-gray-900">{{ $typeLabels[$contrat['type'] ?? 'cdi'] ?? 'Contrat de travail' }}</p>
        </td>
    </tr>
</table>

<!-- Date -->
<table class="mb-6">
    <tr>
        <td class="text-right">
            <p class="text-sm text-gray-500">Douala, le {{ \Carbon\Carbon::parse($contrat['date_debut'] ?? now())->format('d/m/Y') }}</p>
        </td>
    </tr>
</table>

<!-- Contenu -->
<table class="mb-6">
    <tr>
        <td>
            <p>Madame, Monsieur,</p>
            <p class="mt-2">En référence à nos échanges et suite à votre recrutement au sein de notre entreprise, nous avons le plaisir de vous confirmer votre engagement selon les modalités ci-dessous :</p>
        </td>
    </tr>
</table>

<!-- Dispositions du contrat -->
<table class="bg-gray-50 border rounded-lg mb-6 w-100">
    <tr>
        <td style="padding: 10px;">
            <h3 class="font-semibold text-gray-900 mb-2">Dispositions du contrat</h3>
            <table>
                <tr>
                    <td class="w-50"><strong>Date de début :</strong> {{ \Carbon\Carbon::parse($contrat['date_debut'] ?? now())->format('d/m/Y') }}</td>
                    @if(!empty($contrat['date_fin']))
                    <td class="w-50"><strong>Date de fin :</strong> {{ \Carbon\Carbon::parse($contrat['date_fin'])->format('d/m/Y') }}</td>
                    @endif
                </tr>
                <tr>
                    <td><strong>Poste :</strong> {{ $employe['poste'] ?? 'Non spécifié' }}</td>
                    <td><strong>Salaire brut mensuel :</strong> {{ number_format($contrat['salaire_brut'] ?? $contrat['salaire_base'] ?? 0, 0, ',', ' ') }} XAF</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Articles -->
<table class="mb-4">
    <tr>
        <td class="w-50">
            <h3 class="font-semibold text-gray-900">Article 1 - Engagement</h3>
            <p>Vous êtes engagé en qualité de {{ $employe['poste'] ?? 'collaborateur' }} au sein de notre établissement. Votre engagement prend effet à compter du {{ \Carbon\Carbon::parse($contrat['date_debut'] ?? now())->format('d/m/Y') }}.
            @if(!empty($contrat['date_fin'])) et prendra fin le {{ \Carbon\Carbon::parse($contrat['date_fin'])->format('d/m/Y') }}. @endif
            @if(($contrat['type'] ?? 'cdi') === 'cdi') pour une durée indéterminée. @endif</p>
        </td>
        <td class="w-50">
            <h3 class="font-semibold text-gray-900">Article 2 - Période d'essai</h3>
            <p>Le présent contrat est soumis à une période d'essai de 
            @if(($contrat['type'] ?? 'cdi') === 'cdi') 2 mois 
            @elseif(($contrat['type'] ?? 'cdi') === 'cdd') 1 mois 
            @else 15 jours @endif.</p>
        </td>
    </tr>
    <tr>
        <td>
            <h3 class="font-semibold text-gray-900">Article 3 - Lieu de travail</h3>
            <p>Votre lieu de travail est situé à nos bureaux de Douala, ou tout autre lieu désigné par la direction dans le cadre de vos fonctions.</p>
        </td>
        <td>
            <h3 class="font-semibold text-gray-900">Article 4 - Durée du travail</h3>
            <p>La durée du travail est fixée à 35 heures hebdomadaires, réparties sur 5 jours ouvrables, conformément à la législation en vigueur.</p>
        </td>
    </tr>
    <tr>
        <td>
            <h3 class="font-semibold text-gray-900">Article 5 - Rémunération</h3>
            <p>En contrepartie de votre travail, vous percevrez un salaire brut mensuel de {{ number_format($contrat['salaire_brut'] ?? $contrat['salaire_base'] ?? 0, 0, ',', ' ') }} XAF.</p>
        </td>
        <td>
            <h3 class="font-semibold text-gray-900">Article 6 - Congés payés</h3>
            <p>Vous bénéficiez des congés payés annuels dans les conditions prévues par la convention collective applicable à notre entreprise.</p>
        </td>
    </tr>
    <tr>
        <td>
            <h3 class="font-semibold text-gray-900">Article 7 - Obligation de discrétion</h3>
            <p>Vous vous engagez à ne pas divulguer, notamment pendant la durée de votre engagement et après sa cessation, aucune information confidentielle dont vous auriez connaissance dans l'exercice de vos fonctions.</p>
        </td>
        <td>
            <h3 class="font-semibold text-gray-900">Article 8 - Clause de non-concurrence</h3>
            <p>Pendant la durée de votre contrat et pour une période de 6 mois après sa cessation, vous vous interdirez, sans l'autorisation écrite de l'entreprise, d'exercer une activité concurrente ou de travailler pour une entreprise concurrente.</p>
        </td>
    </tr>
</table>

<!-- Mention finale -->
<table class="bg-gray-50 border rounded-lg mb-6">
    <tr>
        <td style="padding: 10px;">
            <p class="text-sm text-gray-600">Le présent contrat est établi en deux exemplaires originaux, dont un vous est remis et l'autre conservé par l'employeur.</p>
        </td>
    </tr>
</table>

<!-- Signatures -->
<table class="border-top pt-6">
    <tr>
        <td class="w-50 text-center">
            <p class="text-sm text-gray-500 mb-2">L'Employeur</p>
            <p class="font-semibold text-gray-900 mb-8">HRManager</p>
            <div class="border-top pt-2">
                <p class="text-sm text-gray-600">Signature</p>
            </div>
        </td>
        <td class="w-50 text-center">
            <p class="text-sm text-gray-500 mb-2">Le Salarié</p>
            <p class="font-semibold text-gray-900 mb-8">{{ $employe['prenom'] ?? '' }} {{ $employe['nom'] ?? '' }}</p>
            <div class="border-top pt-2">
                <p class="text-sm text-gray-600">Signature</p>
            </div>
        </td>
    </tr>
</table>

</body>
</html>