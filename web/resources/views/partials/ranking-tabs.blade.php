@php $qs = ($showAll ?? false) ? '?show=all' : ''; @endphp
<div class="seg">
    <a href="/ranking/absolute{{ $qs }}" class="btn {{ $current === 'absolute' ? 'active' : '' }}">Skupna</a>
    <a href="/ranking/leaders{{ $qs }}" class="btn {{ $current === 'leader' ? 'active' : '' }}">Leaders</a>
    <a href="/ranking/followers{{ $qs }}" class="btn {{ $current === 'follower' ? 'active' : '' }}">Followers</a>
</div>
