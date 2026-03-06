<!DOCTYPE html>
<html>
<body>
    <p>Hi {{ $user->name }},</p>

    <p>An account has been created for you. You can log in with the following credentials:</p>

    <p>
        <strong>Email:</strong> {{ $user->email }}<br>
        <strong>Password:</strong> {{ $plainPassword }}
    </p>

    <p>Please change your password after logging in.</p>

    <p><a href="{{ url('/') }}">{{ url('/') }}</a></p>
</body>
</html>
