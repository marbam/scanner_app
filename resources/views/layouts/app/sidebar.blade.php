<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="chart-bar" :href="route('pulse')" :current="request()->routeIs('pulse')" target="_blank">
                        {{ __('Pulse') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-magnifying-glass" :href="route('scans.index')" :current="request()->routeIs('scans.index')" wire:navigate>
                        {{ __('Scans') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="megaphone" :href="route('planning-applications.index')" :current="request()->routeIs('planning-applications.index')" wire:navigate>
                        {{ __('Bristol adverts') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Facebook')" class="grid">
                    <flux:sidebar.item icon="document-text" :href="route('facebook.posts.index')" :current="request()->routeIs('facebook.posts.index')" wire:navigate>
                        {{ __('All posts') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="clock" :href="route('facebook.memories.index')" :current="request()->routeIs('facebook.memories.index')" wire:navigate>
                        {{ __('Memories') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Twitter')" class="grid">
                    <flux:sidebar.item icon="clock" :href="route('twitter.memories.index')" :current="request()->routeIs('twitter.memories.index')" wire:navigate>
                        {{ __('Memories') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Habit Tracker')" class="grid">
                    <flux:sidebar.item icon="calendar-days" :href="route('habits.log')" :current="request()->routeIs('habits.log')" wire:navigate>
                        {{ __('Log') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="squares-2x2" :href="route('habits.tracker')" :current="request()->routeIs('habits.tracker')" wire:navigate>
                        {{ __('Tracker') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="arrow-trending-up" :href="route('habits.summary')" :current="request()->routeIs('habits.summary')" wire:navigate>
                        {{ __('Summary') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('habits.activities')" :current="request()->routeIs('habits.activities')" wire:navigate>
                        {{ __('Activities') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Play')" class="grid">
                    <flux:sidebar.item icon="squares-2x2" :href="route('squares')" :current="request()->routeIs('squares')" wire:navigate>
                        {{ __('Squares') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="sparkles" :href="route('amoeba')" :current="request()->routeIs('amoeba')" wire:navigate>
                        {{ __('Amoeba') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
