(function(){
    const root = document.documentElement;
    const body = document.body;
    const THEME_KEY = 's24_theme';
    const defaultTheme = 'theme-purple';
    const themeColors = {
        'theme-purple': '#6d28d9',
        'theme-emerald': '#10b981',
        'theme-rose': '#e11d48',
        'theme-amber': '#f59e0b',
        'theme-slate': '#334155',
        'theme-cyan': '#06b6d4',
        'theme-pink': '#ec4899'
    };

    function applyTheme(theme){
        const themes = ['theme-emerald','theme-rose','theme-amber','theme-slate','theme-purple','theme-cyan','theme-pink'];
        root.classList.remove(...themes);
        body && body.classList.remove(...themes);
        root.classList.add(theme);
        body && body.classList.add(theme);
        localStorage.setItem(THEME_KEY, theme);
        // Update current swatch if present
        const indicator = document.querySelector('[data-theme-current]');
        if (indicator){
            const color = themeColors[theme] || themeColors[defaultTheme];
            indicator.style.background = color;
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        const saved = localStorage.getItem(THEME_KEY) || defaultTheme;
        applyTheme(saved);
        
        const picker = document.querySelector('[data-theme-picker]');
        if (picker){
            const menu = picker.querySelector('.theme-menu');
            picker.querySelector('[data-toggle]').addEventListener('click', ()=>{
                menu.classList.toggle('open');
                // Support pages where menu starts with 'hidden'
                menu.classList.toggle('hidden');
            });
            menu.querySelectorAll('[data-theme]').forEach(el=>{
                el.addEventListener('click', ()=>{
                    applyTheme(el.getAttribute('data-theme'));
                    menu.classList.remove('open');
                    menu.classList.add('hidden');
                });
            });
        }

        // Hero rotation (if any hero has data-hero-images)
        document.querySelectorAll('[data-hero-images]').forEach(hero=>{
            try{
                const images = JSON.parse(hero.getAttribute('data-hero-images'));
                let i = 0;
                if (Array.isArray(images) && images.length){
                    hero.style.backgroundImage = `url(${images[0]})`;
                    setInterval(()=>{
                        i = (i+1) % images.length;
                        hero.style.backgroundImage = `url(${images[i]})`;
                    }, 5000);
                }
            }catch(e){}
        });
    });
})();