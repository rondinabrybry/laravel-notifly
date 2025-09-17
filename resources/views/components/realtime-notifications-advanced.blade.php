@props([
    'theme' => 'default',
    'showAvatar' => true,
    'enableSound' => true,
    'position' => 'top-right',
    'maxNotifications' => 5,
    'autoClose' => true,
    'autoCloseDelay' => 5000,
    'enablePersistence' => false,
    'containerClass' => ''
])

<div 
    id="laravel-notify-container" 
    class="fixed z-50 pointer-events-none {{ $getPositionClasses() }} {{ $containerClass }}"
    data-config="{{ json_encode(array_merge($config, [
        'theme' => $theme,
        'showAvatar' => $showAvatar,
        'enableSound' => $enableSound,
        'position' => $position,
        'maxNotifications' => $maxNotifications,
        'autoClose' => $autoClose,
        'autoCloseDelay' => $autoCloseDelay,
        'enablePersistence' => $enablePersistence,
        'themeClasses' => $getThemeClasses(),
        'animationConfig' => $getAnimationConfig(),
        'soundConfig' => $getSoundConfig(),
    ])) }}"
>
    <!-- Toast notifications will be inserted here dynamically -->
</div>

<!-- Notification Templates -->
<template id="notification-template-default">
    <div class="notification-toast pointer-events-auto w-full max-w-sm bg-white shadow-lg rounded-lg border border-gray-200 mb-4 overflow-hidden">
        <div class="notification-progress absolute top-0 left-0 h-1 bg-blue-500 transition-all duration-linear" style="width: 100%;"></div>
        
        <div class="p-4">
            <div class="flex items-start">
                <div class="notification-avatar flex-shrink-0 mr-3" style="display: {{ $showAvatar ? 'block' : 'none' }}">
                    <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center notification-avatar-placeholder">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM9 7H4l5-5v5z"/>
                        </svg>
                    </div>
                    <img class="notification-avatar-img w-10 h-10 rounded-full hidden" alt="Avatar">
                </div>
                
                <div class="flex-1 min-w-0">
                    <div class="notification-header flex items-center justify-between mb-1">
                        <p class="notification-title text-sm font-semibold text-gray-900"></p>
                        <span class="notification-time text-xs text-gray-500 ml-2"></span>
                    </div>
                    
                    <p class="notification-message text-sm text-gray-600 leading-relaxed"></p>
                    
                    <div class="notification-actions mt-3 hidden">
                        <div class="flex space-x-2">
                            <!-- Action buttons will be inserted here -->
                        </div>
                    </div>
                </div>
                
                <div class="flex-shrink-0 ml-4">
                    <button 
                        type="button" 
                        class="notification-close inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        onclick="window.LaravelNotify.closeNotification(this)"
                    >
                        <span class="sr-only">Close</span>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="notification-template-minimal">
    <div class="notification-toast pointer-events-auto w-full max-w-sm bg-white shadow-lg rounded-lg mb-4 overflow-hidden border-l-4">
        <div class="notification-progress absolute top-0 left-0 h-1 bg-blue-500 transition-all duration-linear" style="width: 100%;"></div>
        
        <div class="p-3">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="notification-title text-sm font-medium text-gray-900"></p>
                    <p class="notification-message text-sm text-gray-600 mt-1"></p>
                </div>
                <button 
                    type="button" 
                    class="notification-close ml-4 text-gray-400 hover:text-gray-600"
                    onclick="window.LaravelNotify.closeNotification(this)"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<template id="notification-template-material">
    <div class="notification-toast pointer-events-auto w-full max-w-sm bg-white shadow-md rounded-lg mb-4 overflow-hidden transform transition-all">
        <div class="notification-progress absolute top-0 left-0 h-1 bg-blue-500 transition-all duration-linear" style="width: 100%;"></div>
        
        <div class="p-4">
            <div class="flex items-start space-x-3">
                <div class="notification-icon flex-shrink-0">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center notification-icon-placeholder">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                
                <div class="flex-1 min-w-0">
                    <h4 class="notification-title text-sm font-medium text-gray-900"></h4>
                    <p class="notification-message text-sm text-gray-500 mt-1"></p>
                    <p class="notification-time text-xs text-gray-400 mt-2"></p>
                </div>
                
                <div class="flex-shrink-0">
                    <button 
                        type="button" 
                        class="notification-close text-gray-400 hover:text-gray-600"
                        onclick="window.LaravelNotify.closeNotification(this)"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Notification Center Modal -->
<div id="notification-center-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modal-title">
                        Notification Center
                    </h3>
                    <button 
                        type="button" 
                        class="text-gray-400 hover:text-gray-600"
                        onclick="window.LaravelNotify.toggleNotificationCenter()"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div id="notification-center-content" class="max-h-96 overflow-y-auto">
                    <div id="notification-center-empty" class="text-center py-8 text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5z" />
                        </svg>
                        <p class="mt-2 text-sm">No notifications yet</p>
                    </div>
                    
                    <div id="notification-center-list" class="space-y-2 hidden">
                        <!-- Notifications will be inserted here -->
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button 
                    type="button" 
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                    onclick="window.LaravelNotify.clearAllNotifications()"
                >
                    Clear All
                </button>
                <button 
                    type="button" 
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                    onclick="window.LaravelNotify.toggleNotificationCenter()"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript Client -->
<script>
    // Initialize advanced notification system
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('laravel-notify-container');
        const config = JSON.parse(container.dataset.config);
        
        // Initialize LaravelNotify with advanced configuration
        window.LaravelNotify = new LaravelNotifyAdvanced(config);
        window.LaravelNotify.connect();
    });
</script>

@if($enablePersistence)
    <script>
        // Enable localStorage persistence for notifications
        window.LaravelNotify?.enablePersistence(true);
    </script>
@endif