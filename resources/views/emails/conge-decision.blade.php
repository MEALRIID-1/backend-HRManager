@extends('emails.layout')

@section('title', $isApprouve ? 'Congé approuvé' : 'Congé refusé')

@section('content')
    @if($isApprouve)
        <h2>✅ Votre demande de congé a été approuvée</h2>
        
        <p>Bonjour {{ $employe->prenom }},</p>
        
        <p>Bonne nouvelle ! Votre demande de congé a été <strong>approuvée</strong>.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <span class="badge badge-success" style="font-size: 18px; padding: 12px 24px;">✓ APPROUVÉ</span>
        </div>
    @else
        <h2>❌ Votre demande de congé a été refusée</h2>
        
        <p>Bonjour {{ $employe->prenom }},</p>
        
        <p>Nous sommes désolés de vous informer que votre demande de congé a été <strong>refusée</strong>.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <span class="badge badge-danger" style="font-size: 18px; padding: 12px 24px;">✗ REFUSÉ</span>
        </div>
    @endif
    
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
        </table>
    </div>
    
    @if(!$isApprouve && $motifRefus)
    <div class="alert alert-warning">
        <h4>📝 Motif du refus :</h4>
        <p style="font-style: italic; margin: 10px 0;">"{{ $motifRefus }}"</p>
        @if($decideur)
        <p style="font-size: 14px; color: #92400E; margin-top: 10px;">
            Décidé par : {{ $decideur->prenom }} {{ $decideur->nom }}
        </p>
        @endif
    </div>
    
    <p>Si vous avez des questions concernant cette décision, n'hésitez pas à contacter votre manager ou le service des ressources humaines.</p>
    @endif
    
    @if($isApprouve)
    <div class="alert alert-info">
        <h4>📌 À retenir :</h4>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>Votre congé est confirmé du <strong>{{ $conge->date_debut->format('d/m/Y') }}</strong> au <strong>{{ $conge->date_fin->format('d/m/Y') }}</strong></li>
            <li>Assurez-vous de bien organiser la passation avec vos collègues avant votre départ</li>
            <li>N'oubliez pas d'activer votre message d'absence sur votre messagerie</li>
        </ul>
    </div>
    
    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/mes-conges" class="btn">Voir mes congés</a>
    </p>
    
    <p>Profitez bien de votre temps de repos ! 🌴</p>
    @endif
    
    <p>Cordialement,<br>
    <strong>L'équipe HRManager</strong></p>
@endsection
