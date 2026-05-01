@extends('emails.layout')

@section('title', 'Nouvelle demande de congé')

@section('content')
    <h2>Nouvelle demande de congé en attente</h2>
    
    <p>Bonjour {{ $validateur->prenom }},</p>
    
    <p>L'employé <strong>{{ $employe->prenom }} {{ $employe->nom }}</strong> ({{ $employe->poste }}) a déposé une nouvelle demande de congé qui nécessite votre validation.</p>
    
    <div class="info-box">
        <h3>📋 Détails de la demande</h3>
        <table class="data-table">
            <tr>
                <th>Type de congé</th>
                <td>{{ $conge->type }}</td>
            </tr>
            <tr>
                <th>Date de début</th>
                <td>{{ $conge->date_debut->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <th>Date de fin</th>
                <td>{{ $conge->date_fin->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <th>Durée</th>
                <td><strong>{{ $duree }} jour(s)</strong></td>
            </tr>
            <tr>
                <th>Statut</th>
                <td><span class="badge badge-warning">En attente</span></td>
            </tr>
            @if($conge->commentaire)
            <tr>
                <th>Commentaire</th>
                <td>{{ $conge->commentaire }}</td>
            </tr>
            @endif
        </table>
    </div>
    
    <div style="text-align: center;">
        <p>Connectez-vous à l'application pour valider ou refuser cette demande :</p>
        <p>
            <a href="{{ $validationUrl }}" class="btn">Traiter la demande</a>
        </p>
    </div>
    
    <div class="alert alert-info">
        <strong>⏰ Délais de validation :</strong>
        <p style="margin: 10px 0;">Merci de traiter cette demande dans les meilleurs délais afin de permettre à l'employé de planifier ses congés.</p>
    </div>
    
    <p>Cordialement,<br>
    <strong>L'équipe HRManager</strong></p>
@endsection
