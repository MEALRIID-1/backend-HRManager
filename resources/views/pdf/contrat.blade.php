<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat de Travail - {{ $entreprise['nom'] }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2c3e50;
        }
        .header h1 {
            color: #2c3e50;
            font-size: 18px;
            margin: 0;
        }
        .company-info {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        .reference {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 20px;
        }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 30px 0;
            text-transform: uppercase;
        }
        .section {
            margin: 20px 0;
        }
        .section-title {
            font-weight: bold;
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 10px;
            text-decoration: underline;
        }
        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 10px;
        }
        .footer {
            margin-top: 50px;
            font-size: 9px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $entreprise['nom'] }}</h1>
        <div class="company-info">
            {{ $entreprise['adresse'] }}<br>
            {{ $entreprise['ville'] }}<br>
            SIRET : {{ $entreprise['siret'] }}
        </div>
    </div>

    <div class="reference">
        Référence : {{ $reference }}<br>
        Date de génération : {{ $date_generation }}
    </div>

    <div class="title">
        Contrat de Travail {{ $contrat['type'] }}
    </div>

    <div class="section">
        <div class="section-title">ENTRE LES SOUSSIGNÉS</div>
        <p>
            <strong>{{ $entreprise['nom'] }}</strong>, représentée par son représentant légal,<br>
            ci-après dénommée "l'Employeur",<br><br>
            <strong>D'une part,</strong>
        </p>
        <p>
            <strong>Et</strong>
        </p>
        <p>
            <strong>{{ $employe['prenom'] }} {{ $employe['nom'] }}</strong>,<br>
            résidant à l'adresse connue de l'employeur,<br>
            ci-après dénommé(e) "l'Employé(e)",<br><br>
            <strong>D'autre part,</strong>
        </p>
    </div>

    <div class="section">
        <div class="section-title">IL A ÉTÉ CONVENU CE QUI SUIT</div>
        <p>
            L'Employeur engage l'Employé(e) en qualité de <strong>{{ $contrat['type'] }}</strong> 
            à compter du <strong>{{ \Carbon\Carbon::parse($contrat['date_debut'])->format('d/m/Y') }}</strong>
            @if($contrat['date_fin'])
                jusqu'au <strong>{{ \Carbon\Carbon::parse($contrat['date_fin'])->format('d/m/Y') }}</strong>.
            @else
                pour une durée indéterminée.
            @endif
        </p>
        <p>
            Le salaire de base mensuel brut est fixé à <strong>{{ number_format($contrat['salaire_base'], 2, ',', ' ') }} €</strong>.
        </p>
        <p>
            L'Employé(e) sera rattaché(e) au département : <strong>{{ $employe['departement'] ?? 'Non précisé' }}</strong>.
        </p>
        <p>
            L'Employé(e) s'engage à respecter le règlement intérieur de l'entreprise et à accomplir les missions qui lui seront confiées dans le cadre de ses fonctions.
        </p>
    </div>

    <div class="section">
        <div class="section-title">CONDITIONS DE RÉMUNÉRATION</div>
        <p>
            La rémunération sera versée mensuellement par virement sur le compte bancaire suivant :<br>
            <strong>IBAN : {{ $employe['iban'] ?? 'Non renseigné' }}</strong>
        </p>
    </div>

    <div class="section">
        <div class="section-title">CLAUSE DE CONFIDENTIALITÉ</div>
        <p>
            L'Employé(e) s'engage à garder confidentielle toute information concernant l'entreprise, 
            ses clients, ses fournisseurs et ses partenaires. Cette obligation de confidentialité 
            reste applicable après la fin du présent contrat.
        </p>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Signature de l'Employeur<br>
                (précédée de la mention "Lu et approuvé")
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Signature de l'Employé(e)<br>
                (précédée de la mention "Lu et approuvé")
            </div>
        </div>
    </div>

    <div class="footer">
        Ce document est généré automatiquement et constitue une version officielle du contrat de travail.<br>
        {{ $entreprise['nom'] }} - Tous droits réservés.
    </div>
</body>
</html>
