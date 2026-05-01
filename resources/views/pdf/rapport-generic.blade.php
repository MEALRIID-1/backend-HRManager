<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport HRManager</title>
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
        .content {
            margin-top: 20px;
        }
        .footer {
            margin-top: 30px;
            font-size: 9px;
            text-align: center;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Rapport {{ ucfirst($type) }}</div>
        <div class="subtitle">Généré le {{ $dateGeneration }}</div>
    </div>

    <div class="content">
        @if(is_array($data))
            <table>
                @foreach($data as $key => $value)
                    <tr>
                        <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                        <td>
                            @if(is_array($value) || is_object($value))
                                {{ json_encode($value) }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @else
            <p>{{ $data }}</p>
        @endif
    </div>

    <div class="footer">
        HRManager - Rapport généré automatiquement
    </div>
</body>
</html>
