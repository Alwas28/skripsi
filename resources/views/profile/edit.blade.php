<x-app-layout>
    <x-slot name="header">Profil</x-slot>

    <div style="display:flex;flex-direction:column;gap:20px;max-width:640px">
        <div class="card animate-fadeUp">
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card animate-fadeUp d1">
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card animate-fadeUp d2">
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
