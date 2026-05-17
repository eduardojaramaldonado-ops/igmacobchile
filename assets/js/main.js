const slides = document.querySelectorAll('.slide');

let current = 0;

function changeSlide(){

    slides[current].classList.remove('active');

    current++;

    if(current >= slides.length){
        current = 0;
    }

    slides[current].classList.add('active');

}

setInterval(changeSlide, 5000);

const menuToggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('.nav');

menuToggle.addEventListener('click', () => {
    nav.classList.toggle('active');
});

// Cerrar menú al hacer clic fuera
document.addEventListener('click', (e) => {
    if(nav && nav.classList.contains('active') && !nav.contains(e.target) && !menuToggle.contains(e.target)){
        nav.classList.remove('active');
    }
});

// Dropdowns colapsables en mobile
document.querySelectorAll('.has-dropdown > a, .has-subdropdown > a').forEach(link => {
    link.addEventListener('click', (e) => {
        if(window.innerWidth <= 1150){
            const parent = link.parentElement;
            const dropdown = parent.querySelector('.dropdown, .subdropdown');
            if(dropdown){
                e.preventDefault();
                const isOpen = parent.classList.contains('open');
                // Cerrar otros del mismo nivel
                const siblings = parent.parentElement.querySelectorAll(':scope > .has-dropdown, :scope > .has-subdropdown');
                siblings.forEach(s => {
                    if(s !== parent){
                        s.classList.remove('open');
                        const d = s.querySelector('.dropdown, .subdropdown');
                        if(d) d.classList.remove('open');
                    }
                });
                parent.classList.toggle('open', !isOpen);
                dropdown.classList.toggle('open', !isOpen);
            }
        }
    });
});

const counters = document.querySelectorAll('.metrica-item h3');

const runCounter = () => {

    counters.forEach(counter => {

        const target = counter.innerText.replace('+','').replace('K','');

        let count = 0;

        const increment = target / 60;

        const updateCounter = () => {

            count += increment;

            if(count < target){

                if(counter.innerText.includes('K')){

                    counter.innerText = '+' + Math.ceil(count) + 'K';

                }else{

                    counter.innerText = '+' + Math.ceil(count);

                }

                requestAnimationFrame(updateCounter);

            }else{

                if(counter.innerText.includes('K')){

                    counter.innerText = '+' + target + 'K';

                }else{

                    counter.innerText = '+' + target;

                }

            }

        };

        updateCounter();

    });

};

window.addEventListener('load', runCounter);

/* FAQ ACCORDION */

const faqItems = document.querySelectorAll('.faq-item');

faqItems.forEach(item => {

    const btn = item.querySelector('.faq-question');

    btn.addEventListener('click', () => {

        const isActive = item.classList.contains('active');

        faqItems.forEach(i => {
            i.classList.remove('active');
            i.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
        });

        if(!isActive){
            item.classList.add('active');
            btn.setAttribute('aria-expanded', 'true');
        }

    });

});