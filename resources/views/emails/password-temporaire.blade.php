@extends('emails.layout')

@section('title', 'Votre compte HRManager')

@section('content')
    <h2>Bienvenue, {{ $employe->prenom }} !</h2>
    
    <p>Votre compte <strong>HRManager</strong> a été créé avec succès par l'administrateur.</p>
    
    <div class="alert alert-info">
        <strong>Important :</strong> Veuillez noter vos identifiants de connexion et modifier votre mot de passe lors de votre première connexion.
    </div>
    
    <div class="info-box">
        <h3>📝 Vos identifiants de connexion</h3>
        <table class="data-table">
            <tr>
                <th>Email</th>
                <td>{{ $employe->email }}</td>
            </tr>
            <tr>
                <th>Mot de passe temporaire</th>
                <td><code style="background: #fff; padding: 4px 8px; border-radius: 4px; font-size: 16px; letter-spacing: 1px;">{{ $passwordTemporaire }}</code></td>
            </tr>
            <tr>
                <th>Matricule</th>
                <td>{{ $employe->matricule }}</td>
            </tr>
        </table>
    </div>
    
    <p style="text-align: center;">
        <a href="{{ $loginUrl }}" class="btn">Se connecter à HRManager</a>
    </p>
    
    <div class="alert alert-warning">
        <strong>⚠️ Pour votre sécurité :</strong>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>Changez votre mot de passe immédiatement après la première connexion</li>
            <li>Ne partagez jamais vos identifiants avec d'autres personnes</li>
            <li>Utilisez un mot de passe fort (minimum 8 caractères, majuscules, minuscules, chiffres)</li>
        </ul>
    </div>
    
    <p>Si vous n'êtes pas à l'origine de cette demande, veuillez contacter immédiatement l'administrateur du système.</p>
    
    <p>Cordialement,<br>
    <strong>L'équipe HRManager</strong></p>
@endsection
