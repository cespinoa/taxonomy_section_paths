





(function (Drupal) {
  Drupal.behaviors.taxonomyStickyNavbar = {
    attach(context, settings) {
      console.log('Navbar sticky behavior attached');
      const navbar = context.querySelector('[data-sticky-navbar]');
      const sentinel = context.querySelector('#sticky-sentinel');
      const observer = new IntersectionObserver(
        ([entry]) => {
          navbar.classList.toggle('is-sticky', !entry.isIntersecting);
        },
        {
          rootMargin: '0px 0px 0px 0px', // Ajusta según la barra de administración.
          threshold: 0,
        }
      );

      observer.observe(sentinel);

      
    }
  };
})(Drupal);
