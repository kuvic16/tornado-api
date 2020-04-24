<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>EasyKnock AI</title>

        <!-- Styles -->
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        <style>
          .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
          }

          @media (min-width: 768px) {
            .bd-placeholder-img-lg {
              font-size: 3.5rem;
            }
          }
        </style>
        <link href="{{ asset('css/signin.css') }}" rel="stylesheet">

    </head>
    <body class="text-center">
        <form class="form-signin" method="POST" action="{{ route('login') }}">
            @csrf
            <img class="mb-4" src="{{ asset('img/EKAI.png') }}">
            <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>

            <label for="email" class="sr-only">{{ __('E-Mail Address') }}</label>
            <input placeholder="{{ __('E-Mail Address') }}" id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

            <label for="password" class="sr-only">{{ __('Password') }}</label>
            <input  placeholder="{{ __('Password') }}" id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror

            <div class="checkbox mb-3">
                <label>
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    {{ __('Remember Me') }}
                </label>
            </div>
            <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
        </form>
    </body>
</html>