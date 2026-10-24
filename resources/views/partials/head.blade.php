<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>
    // HandlemyAP brand — a PayPal-adjacent blue→navy palette and a geometric
    // sans close in feel to PayPal's own type. Our own identity, not a clone.
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                },
                colors: {
                    brand: {
                        50: '#eef5ff', 100: '#d9e8ff', 200: '#b3d0ff', 300: '#80b2ff',
                        400: '#4d94fb', 500: '#1a7fe6', 600: '#0070e0', 700: '#005bc4',
                        800: '#004399', 900: '#001c64',
                    },
                },
            },
        },
    };
</script>
<style>body{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;}</style>
