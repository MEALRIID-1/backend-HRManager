<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin de Paie - {{ $fiche->mois }} {{ $fiche->annee }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            border: 2px solid #333;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        .company-info {
            width: 50%;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a5490;
            margin-bottom: 5px;
        }
        .company-details {
            font-size: 10px;
            color: #666;
        }
        .payslip-title {
            text-align: right;
        }
        .payslip-title h1 {
            font-size: 20px;
            color: #1a5490;
            margin-bottom: 5px;
        }
        .period {
            font-size: 14px;
            font-weight: bold;
        }
        .employee-section {
            border: 1px solid #333;
            padding: 15px;
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            background-color: #e8f4fc;
            padding: 8px;
            margin: -15px -15px 15px -15px;
            border-bottom: 1px solid #333;
        }
        .employee-grid {
            display: flex;
            justify-content: space-between;
        }
        .employee-info, .employment-info {
            width: 48%;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
            color: #555;
        }
        .info-value {
            flex: 1;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .salary-table th {
            background-color: #1a5490;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        .salary-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        .salary-table .amount {
            text-align: right;
        }
        .salary-table .total-row {
            font-weight: bold;
            background-color: #e8f4fc;
        }
        .salary-table .net-row {
            font-weight: bold;
            font-size: 14px;
            background-color: #1a5490;
            color: white;
        }
        .summary-section {
            border: 1px solid #333;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary-grid {
            display: flex;
            justify-content: space-between;
        }
        .summary-box {
            width: 30%;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .summary-label {
            font-size: 10px;
            color: #666;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #1a5490;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #333;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 10px;
            font-size: 10px;
        }
        .legal-notice {
            font-size: 9px;
            color: #666;
            text-align: center;
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .calculation-detail {
            font-size: 9px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- En-tête entreprise --}}
        <div class="header">
            <div class="company-info">
                <div class="company-name">HRManager SAS</div>
                <div class="company-details">
                    123 Avenue des Entreprises<br>
                    75000 Paris, France<br>
                    SIRET: 123 456 789 00012<br>
                    Code NAF: 6201Z
                </div>
            </div>
            <div class="payslip-title">
                <h1>BULLETIN DE PAIE</h1>
                <div class="period">{{ $fiche->mois }} {{ $fiche->annee }}</div>
            </div>
        </div>

        {{-- Informations employé --}}
        <div class="employee-section">
            <div class="section-title">INFORMATIONS EMPLOYÉ</div>
            <div class="employee-grid">
                <div class="employee-info">
                    <div class="info-row">
                        <div class="info-label">Nom:</div>
                        <div class="info-value">{{ $employe->nom }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Prénom:</div>
                        <div class="info-value">{{ $employe->prenom }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Adresse:</div>
                        <div class="info-value">{{ $employe->adresse ?? 'Non renseignée' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Matricule:</div>
                        <div class="info-value">{{ $employe->matricule }}</div>
                    </div>
                </div>
                <div class="employment-info">
                    <div class="info-row">
                        <div class="info-label">Poste:</div>
                        <div class="info-value">{{ $employe->poste ?? 'Non renseigné' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Département:</div>
                        <div class="info-value">{{ $employe->departement ?? 'Non renseigné' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date embauche:</div>
                        <div class="info-value">{{ $employe->date_embauche ? $employe->date_embauche->format('d/m/Y') : 'Non renseignée' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">N° Sécurité sociale:</div>
                        <div class="info-value">{{ $employe->numero_securite_sociale ?? 'Non renseigné' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tableau de paie --}}
        <table class="salary-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Libellé</th>
                    <th style="width: 20%;">Base / Quantité</th>
                    <th style="width: 20%;">Taux</th>
                    <th style="width: 20%;" class="amount">Montant</th>
                </tr>
            </thead>
            <tbody>
                {{-- Salaire de base --}}
                <tr>
                    <td>Salaire de base</td>
                    <td>151.67 h</td>
                    <td>{{ number_format($calculs['salaire_base'] / 151.67, 2, ',', ' ') }} €/h</td>
                    <td class="amount">{{ number_format($calculs['salaire_base'], 2, ',', ' ') }} €</td>
                </tr>

                {{-- Heures supplémentaires --}}
                @if($fiche->heures_sup > 0)
                <tr>
                    <td>Heures supplémentaires (majorées 25%)<br>
                        <span class="calculation-detail">{{ $fiche->heures_sup }} h × {{ number_format($calculs['salaire_base'] / 151.67 * 1.25, 2, ',', ' ') }} €/h</span>
                    </td>
                    <td>{{ $fiche->heures_sup }} h</td>
                    <td>{{ number_format($calculs['salaire_base'] / 151.67 * 1.25, 2, ',', ' ') }} €/h</td>
                    <td class="amount">{{ number_format($calculs['montant_heures_sup'], 2, ',', ' ') }} €</td>
                </tr>
                @endif

                {{-- Absences --}}
                @if($fiche->absences > 0)
                <tr>
                    <td>Absences déduites<br>
                        <span class="calculation-detail">{{ $fiche->absences }} j × {{ number_format($calculs['deduction_absences'] / $fiche->absences, 2, ',', ' ') }} €/j</span>
                    </td>
                    <td>{{ $fiche->absences }} j</td>
                    <td>-{{ number_format($calculs['deduction_absences'] / $fiche->absences, 2, ',', ' ') }} €/j</td>
                    <td class="amount" style="color: red;">-{{ number_format($calculs['deduction_absences'], 2, ',', ' ') }} €</td>
                </tr>
                @endif

                {{-- Total brut --}}
                <tr class="total-row">
                    <td colspan="3"><strong>SALAIRE BRUT</strong></td>
                    <td class="amount"><strong>{{ number_format($calculs['salaire_brut'], 2, ',', ' ') }} €</strong></td>
                </tr>

                {{-- Cotisations salariales --}}
                <tr>
                    <td colspan="4" style="background-color: #f5f5f5; font-weight: bold;">COTISATIONS SALARIALES</td>
                </tr>
                <tr>
                    <td>URSSAF Maladie, Maternité, Invalidité, Décès</td>
                    <td>{{ number_format($calculs['salaire_brut'], 2, ',', ' ') }} €</td>
                    <td>0,75%</td>
                    <td class="amount" style="color: red;">-{{ number_format($calculs['salaire_brut'] * 0.0075, 2, ',', ' ') }} €</td>
                </tr>
                <tr>
                    <td>URSSAF Assurance Vieillesse (plafonnée)</td>
                    <td>{{ number_format(min($calculs['salaire_brut'], 3377), 2, ',', ' ') }} €</td>
                    <td>6,90%</td>
                    <td class="amount" style="color: red;">-{{ number_format(min($calculs['salaire_brut'], 3377) * 0.069, 2, ',', ' ') }} €</td>
                </tr>
                <tr>
                    <td>URSSAF Assurance Vieillesse (déplafonnée)</td>
                    <td>{{ number_format($calculs['salaire_brut'], 2, ',', ' ') }} €</td>
                    <td>0,40%</td>
                    <td class="amount" style="color: red;">-{{ number_format($calculs['salaire_brut'] * 0.004, 2, ',', ' ') }} €</td>
                </tr>
                <tr>
                    <td>Contribution au FNAL</td>
                    <td>{{ number_format($calculs['salaire_brut'], 2, ',', ' ') }} €</td>
                    <td>0,50%</td>
                    <td class="amount" style="color: red;">-{{ number_format($calculs['salaire_brut'] * 0.005, 2, ',', ' ') }} €</td>
                </tr>

                {{-- Total cotisations --}}
                <tr class="total-row">
                    <td colspan="3"><strong>TOTAL COTISATIONS SALARIALES</strong></td>
                    <td class="amount" style="color: red;"><strong>-{{ number_format($calculs['cotisations_salariales'], 2, ',', ' ') }} €</strong></td>
                </tr>

                {{-- Net à payer --}}
                <tr class="net-row">
                    <td colspan="3"><strong>NET À PAYER</strong></td>
                    <td class="amount"><strong>{{ number_format($calculs['net_a_payer'], 2, ',', ' ') }} €</strong></td>
                </tr>
            </tbody>
        </table>

        {{-- Résumé --}}
        <div class="summary-section">
            <div class="section-title">RÉCAPITULATIF</div>
            <div class="summary-grid">
                <div class="summary-box">
                    <div class="summary-label">Salaire brut</div>
                    <div class="summary-value">{{ number_format($calculs['salaire_brut'], 2, ',', ' ') }} €</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Cotisations</div>
                    <div class="summary-value" style="color: red;">-{{ number_format($calculs['cotisations_salariales'], 2, ',', ' ') }} €</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Net à payer</div>
                    <div class="summary-value" style="color: #1a5490;">{{ number_format($calculs['net_a_payer'], 2, ',', ' ') }} €</div>
                </div>
            </div>
        </div>

        {{-- Footer avec signatures --}}
        <div class="footer">
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line">
                        Signature de l'employeur<br>
                        HRManager SAS
                    </div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">
                        Signature de l'employé<br>
                        {{ $employe->prenom }} {{ $employe->nom }}<br>
                        <em>(après vérification du montant net versé)</em>
                    </div>
                </div>
            </div>

            <div class="legal-notice">
                <strong>Mention légale :</strong> Ce bulletin de paie doit être conservé sans limitation de durée. 
                Il est recommandé d'en conserver une copie électronique ou papier. 
                Pour toute question concernant votre paie, contactez le service RH à rh@hrmanager.com.<br>
                Document généré le {{ now()->format('d/m/Y à H:i') }}
            </div>
        </div>
    </div>
</body>
</html>
