<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test yechish</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: {
                            light: '#FFD700',
                            DEFAULT: '#CFB53B',
                            dark: '#B8860B',
                        },
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-black text-gold-light min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md sm:max-w-xl space-y-8 p-6 sm:p-10 bg-gray-900 bg-opacity-80 backdrop-filter backdrop-blur-xl rounded-3xl shadow-2xl border border-gold">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-center text-gold mb-8">
            Testlar bilan ishlash
        </h2>

        <form id="testForm" action="/questions" method="GET" class="space-y-8">
            <!-- Input for range -->
            <div class="flex flex-col space-y-4">
                <label for="rangeMin" class="text-base sm:text-lg font-medium text-gold-light">Sonlar oralig'ini kiriting:</label>
                <div class="flex items-center space-x-4">
                    <input type="number" name="rangeMin" id="rangeMin" placeholder="dan" required class="w-full sm:w-24 p-3 text-black rounded-lg bg-gold-light focus:bg-white transition-all duration-300 focus:ring-2 focus:ring-gold focus:outline-none">
                    <span class="text-xl sm:text-2xl font-bold text-gold">-</span>
                    <input type="number" name="rangeMax" id="rangeMax" placeholder="gacha" required class="w-full sm:w-24 p-3 text-black rounded-lg bg-gold-light focus:bg-white transition-all duration-300 focus:ring-2 focus:ring-gold focus:outline-none">
                </div>
            </div>

            <!-- Category Buttons -->
            <div class="flex flex-col space-y-4">
                <label class="text-base sm:text-lg font-medium text-gold-light">Kategoriyani tanlang:</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <button type="button" onclick="selectCategory('iqtisodiy_talim')" class="category-btn px-4 py-3 bg-gradient-to-r from-gold to-gold-dark text-black hover:from-gold-dark hover:to-gold rounded-lg transition-all duration-300 transform hover:scale-105 shadow-md flex items-center justify-center" data-category="iqtisodiy_talim">
                        <span class="text-sm sm:text-base">Iqtisodiy ta'limotlar</span>
                    </button>
                    <button type="button" onclick="selectCategory('mikro_iqtisod')" class="category-btn px-4 py-3 bg-gradient-to-r from-gold to-gold-dark text-black hover:from-gold-dark hover:to-gold rounded-lg transition-all duration-300 transform hover:scale-105 shadow-md flex items-center justify-center" data-category="mikro_iqtisod">
                        <span class="text-sm sm:text-base">Mikro iqtisodiyot</span>
                    </button>
                    <button type="button" onclick="selectCategory('raqamli_iqtisod')" class="category-btn px-4 py-3 bg-gradient-to-r from-gold to-gold-dark text-black hover:from-gold-dark hover:to-gold rounded-lg transition-all duration-300 transform hover:scale-105 shadow-md flex items-center justify-center" data-category="raqamli_iqtisod">
                        <span class="text-sm sm:text-base">Raqamli iqtisodiyot</span>
                    </button>
                    {{-- <button type="button" onclick="selectCategory('signal')" class="category-btn px-4 py-3 bg-gradient-to-r from-gold to-gold-dark text-black hover:from-gold-dark hover:to-gold rounded-lg transition-all duration-300 transform hover:scale-105 shadow-md flex items-center justify-center" data-category="signal">
                        <span class="text-sm sm:text-base">Tizim va signallar</span>
                    </button> --}}
                </div>
            </div>

            <!-- Hidden input for category -->
            <input type="hidden" name="category" id="categoryInput" required>

            <!-- Submit Button -->
            <button type="submit" class="w-full py-3 px-6 bg-gradient-to-r from-gold to-gold-dark text-black hover:from-gold-dark hover:to-gold rounded-lg text-base sm:text-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-md">
                Boshlash
            </button>
        </form>

        <div id="message" class="mt-6 text-center text-gold-light text-base sm:text-lg hidden"></div>
    </div>

    <script>
        function selectCategory(category) {
            // Set the selected category in the hidden input
            document.getElementById('categoryInput').value = category;
        
            // Reset all buttons to their original color
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.classList.remove('ring-2', 'ring-gold', 'bg-black');
                btn.classList.add('bg-gradient-to-r', 'from-gold', 'to-gold-dark', 'text-black', 'hover:from-gold-dark', 'hover:to-gold');
            });
        
            // Change the color of the selected button
            const selectedBtn = document.querySelector(`[data-category="${category}"]`);
            selectedBtn.classList.remove('bg-gradient-to-r', 'from-gold', 'to-gold-dark', 'text-black', 'hover:from-gold-dark', 'hover:to-gold');
            selectedBtn.classList.add('ring-2', 'ring-gold', 'bg-black', 'text-gold');
        }
        
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
        
            const rangeMin = document.getElementById('rangeMin').value;
            const rangeMax = document.getElementById('rangeMax').value;
            const category = document.getElementById('categoryInput').value;
        
            if (!rangeMin || !rangeMax || !category) {
                alert('Iltimos, barcha maydonlarni to\'ldiring');
                return;
            }
        
            // Joriy URL'dan chat_id ni olish
            const urlParams = new URLSearchParams(window.location.search);
            const chatId = urlParams.get('chat_id');
        
            // URL'ni shakllantirish
            let url = `/questions?start_number=${rangeMin}&end_number=${rangeMax}&test_category=${category}`;
            if (chatId) {
                url += `&chat_id=${chatId}`;
            }
        
            window.location.href = url;
        });
        </script>
</body>
</html>

