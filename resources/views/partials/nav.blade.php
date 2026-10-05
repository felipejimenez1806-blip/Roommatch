<nav>
  <a class="logo" href="{{ route('index') }}">
    <img src="{{ asset('Img/logo_roommatch.png') }}" alt="Roommatch" class="logo-img"/>
  </a>
  <ul class="nav-links">
    <li><a href="{{ url('/habitaciones') }}">Habitaciones</a></li>
    <li><a href="{{ url('/roomies') }}">Roomies</a></li>
    <li><a id="navPopularLink" href="#popularesSection">Popular</a></li>
  </ul>
  <div class="nav-actions" id="navActions">
    <!-- Se pinta dinámicamente desde nav.js según si hay sesión activa (token en localStorage) -->
  </div>
</nav>
