<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport d'Activité</title>
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
        <div class="title">Rapport d'Activité</div>
        <div class="subtitle">
            Généré le {{ $dateGeneration }}
            @if(isset($periode))
                - Période: {{ $periode }}
            @endif
        </div>
    </div>

    @if(isset($data['activites']) && count($data['activites']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Entité</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['activites'] as $activite)
                    <tr>
                        <td>{{ $activite->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $activite->user->nom ?? 'Système' }} {{ $activite->user->prenom ?? '' }}</td>
                        <td>{{ $activite->action }}</td>
                        <td>{{ $activite->entity_name }}</td>
                        <td>{{ Str::limit($activite->description, 50) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Aucune activité enregistrée pour cette période.</p>
    @endif

    <div class="footer">
        HRManager - Rapport généré automatiquement
    </div>
</body>
</html>
