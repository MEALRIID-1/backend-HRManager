<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport des Congés</title>
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
        .statut-en_attente { color: #f39c12; }
        .statut-approuve { color: #27ae60; }
        .statut-refuse { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Rapport des Congés</div>
        <div class="subtitle">
            Généré le {{ $dateGeneration }}
            @if(isset($periode))
                - Période: {{ $periode }}
            @endif
        </div>
    </div>

    @if(isset($data['conges']) && count($data['conges']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Employé</th>
                    <th>Type</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Durée</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['conges'] as $conge)
                    <tr>
                        <td>{{ $conge->employe->nom }} {{ $conge->employe->prenom }}</td>
                        <td>{{ $conge->type }}</td>
                        <td>{{ $conge->date_debut->format('d/m/Y') }}</td>
                        <td>{{ $conge->date_fin->format('d/m/Y') }}</td>
                        <td>{{ $conge->date_debut->diffInDays($conge->date_fin) + 1 }} jours</td>
                        <td class="statut-{{ $conge->etat }}">{{ $conge->etat }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if(isset($data['statistiques']))
            <div style="margin-top: 20px;">
                <h4>Statistiques</h4>
                <p>Total congés: {{ $data['statistiques']['total'] ?? count($data['conges']) }}</p>
                <p>En attente: {{ $data['statistiques']['en_attente'] ?? 0 }}</p>
                <p>Approuvés: {{ $data['statistiques']['approuves'] ?? 0 }}</p>
                <p>Refusés: {{ $data['statistiques']['refuses'] ?? 0 }}</p>
            </div>
        @endif
    @else
        <p>Aucune donnée disponible pour cette période.</p>
    @endif

    <div class="footer">
        HRManager - Rapport généré automatiquement
    </div>
</body>
</html>
