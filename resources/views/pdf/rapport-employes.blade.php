<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport des Employés</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 12px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #2c3e50;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 10px;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            font-size: 9px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Rapport des Employés</div>
        <div class="subtitle">Généré le {{ $dateGeneration }}</div>
    </div>

    @if(isset($data['employes']) && count($data['employes']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Matricule</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Poste</th>
                    <th>Département</th>
                    <th>Email</th>
                    <th>Date d'embauche</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['employes'] as $employe)
                    <tr>
                        <td>{{ $employe->matricule }}</td>
                        <td>{{ $employe->nom }}</td>
                        <td>{{ $employe->prenom }}</td>
                        <td>{{ $employe->poste ?? 'Non défini' }}</td>
                        <td>{{ $employe->departement ?? 'Non défini' }}</td>
                        <td>{{ $employe->email }}</td>
                        <td>{{ $employe->date_embauche ? $employe->date_embauche->format('d/m/Y') : 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <h4>Résumé</h4>
            <p>Total employés: {{ count($data['employes']) }}</p>
        </div>
    @else
        <p>Aucune donnée disponible.</p>
    @endif

    <div class="footer">
        HRManager - Rapport généré automatiquement
    </div>
</body>
</html>
