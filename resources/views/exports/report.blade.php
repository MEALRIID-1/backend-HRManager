<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 24px;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 8px;
        }
        .meta {
            font-size: 10px;
            color: #6b7280;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
        }
        .empty {
            margin-top: 16px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <h1>Rapport {{ $type === 'leaves' ? 'des congés' : 'des effectifs' }}</h1>
    <div class="meta">
        Généré le {{ $generatedAt->format('d/m/Y H:i') }}
        @if(!empty($filters['debut']) || !empty($filters['fin']))
            | Période: {{ $filters['debut'] ?? '...' }} -> {{ $filters['fin'] ?? '...' }}
        @endif
    </div>

    @if($rows->isEmpty())
        <div class="empty">Aucune donnée disponible pour cette période.</div>
    @else
        @php
            $first = (array) $rows->first();
        @endphp
        <table>
            <thead>
                <tr>
                    @foreach(array_keys($first) as $heading)
                        <th>{{ str_replace('_', ' ', ucfirst($heading)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php $rowArray = (array) $row; @endphp
                    <tr>
                        @foreach(array_keys($first) as $key)
                            <td>{{ is_scalar($rowArray[$key] ?? '') ? $rowArray[$key] : json_encode($rowArray[$key] ?? '', JSON_UNESCAPED_UNICODE) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
