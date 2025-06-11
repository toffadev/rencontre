<template>
  <div class="text-gray-800">
    <!-- Header -->
    <header class="gradient-bg text-white shadow-lg">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-center space-x-2">
          <i class="fas fa-heart text-2xl"></i>
          <h1 class="text-2xl font-bold">HeartMatch</h1>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="min-h-[calc(100vh-132px)]">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-6">
      <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
          <div class="flex items-center space-x-2 mb-4 md:mb-0">
            <i class="fas fa-heart text-pink-500"></i>
            <span class="font-medium">HeartMatch</span>
          </div>
          <div class="flex space-x-6">
            <a href="#" class="text-gray-600 hover:text-pink-500 text-sm">Confidentialité</a>
            <a href="#" class="text-gray-600 hover:text-pink-500 text-sm">Conditions</a>
            <a href="#" class="text-gray-600 hover:text-pink-500 text-sm">Aide</a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
// Guest layout component logic
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

.gradient-bg {
  background: linear-gradient(135deg, #f9a8d4 0%, #f472b6 100%);
}

.input-field:focus {
  box-shadow: 0 0 0 3px rgba(244, 114, 182, 0.3);
}

.login-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.divider {
  display: flex;
  align-items: center;
  text-align: center;
  color: #9ca3af;
}

.divider::before, .divider::after {
  content: "";
  flex: 1;
  border-bottom: 1px solid #e5e7eb;
}

.divider::before {
  margin-right: 1rem;
}

.divider::after {
  margin-left: 1rem;
}

.forgot-password:hover {
  text-decoration: underline;
}
</style> 