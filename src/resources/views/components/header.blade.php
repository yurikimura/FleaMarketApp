<header class="header">
    <div class="header__logo">
        <a href="/"><img src="{{ asset('img/logo.png') }}" alt="Coachtech ロゴ"></a>
    </div>
    @if (!request()->is('login') && !request()->is('register'))
        <form action="/search" method="get" class="header__search">
            <input type="text" name="query" placeholder="なにをお探しですか？" class="header__search-input">
        </form>
        <nav class="header__nav">
            <ul>
                @if(Auth::check())
                <li>
                    <form action="/logout" method="post">
                        @csrf
                        <button class="header__logout">ログアウト</button>
                    </form>
                </li>
                <li><a href="/mypage">マイページ</a></li>
                @else
                <li><a href="/login">ログイン</a></li>
                <li><a href="/register">会員登録</a></li>
                @endif
                <a href="/sell"><li class="header__btn">出品</li></a>
            </ul>
        </nav>
    @endif
</header>
