/* Fetches assets/data/content.json and renders the Gallery, Testimonial and
   Blog sections from it. Image files are NOT stored in the JSON — they are
   expected to live in assets/img/{gallery,testimonial,blog}/ named after each
   item's id (e.g. assets/img/gallery/gallery-1.jpg).

   main.js initializes the owlCarousel/slick sliders and AOS animations for
   these sections, so it must not run until this content actually exists in
   the DOM. Its <script> tag is intentionally left out of index.html and is
   instead loaded here, after rendering finishes (see loadMainScript below). */
(function () {
  var preloaderHidden = false;
  function hidePreloader() {
    if (preloaderHidden) return;
    preloaderHidden = true;
    $('#preloader').fadeOut(300);
  }
  // Absolute safety net: never let the preloader block the page for more than
  // a few seconds, no matter what fails upstream (slow network, a fetch that
  // never settles, main.js failing to load). Without this, a stalled request
  // leaves the full-screen preloader up forever.
  setTimeout(hidePreloader, 6000);

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderGallery(items) {
    const $container = $('.cases-study-slider-area');
    if (!$container.length) return;
    items.forEach(function (item) {
      const imgSrc = 'assets/img/gallery/gallery-' + item.id + '.jpg';
      const altText = item.title
        ? item.title + ' — The Brotherhood'
        : 'Legal case gallery photo by The Brotherhood';
      let textHtml = '';
      if (item.title) {
        textHtml =
          '<div class="case5-text">' +
            '<a href="#casestudy">' + escapeHtml(item.title) + '</a>' +
            '<p>' + escapeHtml(item.caption) + '</p>' +
          '</div>';
      }
      $container.append(
        '<div class="case-slider-boxarea">' +
          '<div class="img1"><img src="' + imgSrc + '" alt="' + escapeHtml(altText) + '"></div>' +
          textHtml +
        '</div>'
      );
    });
  }

  function renderTestimonials(items) {
    const $images = $('.slider-images1');
    const $text = $('.tes2-slider-all1');
    if (!$images.length || !$text.length) return;
    items.forEach(function (item) {
      const imgSrc = 'assets/img/testimonial/testimonial-' + item.id + '.jpg';
      $images.append(
        '<div class="single-slider-images">' +
          '<div class="img1 img100"><img src="' + imgSrc + '" alt="Satisfied client of The Brotherhood law firm"></div>' +
        '</div>'
      );

      const starCount = Number(item.rating) || 5;
      let stars = '';
      for (let i = 0; i < starCount; i++) {
        stars += '<li><i class="fa-solid fa-star"></i></li>';
      }

      $text.append(
        '<div class="tes2-single-slider">' +
          '<div class="ratting"><ul>' + stars + '</ul></div>' +
          '<div class="space10"></div>' +
          '<div class="hadding">' +
            '<a>' + escapeHtml(item.name) + '</a>' +
            '<div class="space5"></div>' +
            '<p>' + escapeHtml(item.role) + '</p>' +
          '</div>' +
          '<div class="space16"></div>' +
          '<div class="main-hadding"><p>&quot;' + escapeHtml(item.text) + '&quot;</p></div>' +
          '<div class="space24"></div>' +
        '</div>'
      );
    });
  }

  function formatDate(isoDate) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(isoDate || ''))) return isoDate || '';
    const parts = isoDate.split('-').map(Number);
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  function paragraphsHtml(text) {
    const blocks = String(text || '').split(/\n\s*\n/).map(function (p) { return p.trim(); }).filter(Boolean);
    if (!blocks.length) return '';
    return blocks
      .map(function (p) { return '<p>' + escapeHtml(p).replace(/\n/g, '<br>') + '</p>'; })
      .join('');
  }

  function openBlogModal(item, imgSrc) {
    $('#modalBlogPostLabel').text(item.title || '');
    $('#modalBlogPost .blog-post-modal-date').text(formatDate(item.date));
    $('#modalBlogPost .blog-post-modal-img').attr('src', imgSrc).attr('alt', item.title || '');
    const body = item.content && item.content.trim() ? paragraphsHtml(item.content) : paragraphsHtml(item.excerpt);
    $('#modalBlogPost .blog-post-modal-content').html(body);

    // Up to 3 extra photos (assets/img/blog/blog-{id}-2.jpg .. -4.jpg). Their
    // existence isn't tracked in content.json, so just try loading each and
    // drop the ones that 404.
    const $extra = $('#modalBlogPost .blog-post-modal-extra-photos').empty();
    [2, 3, 4].forEach(function (slot) {
      const $img = $('<img>').attr('src', 'assets/img/blog/blog-' + item.id + '-' + slot + '.jpg').attr('alt', '');
      $img.on('error', function () { $(this).remove(); });
      $extra.append($img);
    });

    $('#modalBlogPost').modal('show');
  }

  function renderBlogs(items) {
    const $container = $('.articles-row');
    if (!$container.length) return;

    if (!items.length) {
      $('.articles-section-area').hide();
      $('a[href="#blogs"]').closest('li').hide();
      return;
    }

    items.forEach(function (item, index) {
      const imgSrc = 'assets/img/blog/blog-' + item.id + '.jpg';
      const delay = 800 + index * 200;
      const $col = $(
        '<div class="col-lg-4 col-md-6" data-aos="fade-left" data-aos-duration="' + delay + '">' +
          '<div class="article-card" role="button" tabindex="0">' +
            '<div class="article-img"><img src="' + imgSrc + '" alt="' + escapeHtml(item.title) + '"></div>' +
            '<div class="space16"></div>' +
            '<div class="article-text">' +
              '<div class="date">' +
                '<span><img src="assets/img/icons/calender.svg" alt=""></span>' +
                '<span class="article-date-text">' + escapeHtml(formatDate(item.date)) + '</span>' +
              '</div>' +
              '<div class="space16"></div>' +
              '<h3>' + escapeHtml(item.title) + '</h3>' +
              '<div class="space12"></div>' +
              '<p>' + escapeHtml(item.excerpt) + '</p>' +
            '</div>' +
          '</div>' +
        '</div>'
      );
      const $card = $col.find('.article-card');
      $card.on('click', function () { openBlogModal(item, imgSrc); });
      $card.on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openBlogModal(item, imgSrc);
        }
      });
      $container.append($col);
    });
  }

  function loadMainScript() {
    const script = document.createElement('script');
    script.src = 'assets/js/main.js';
    script.onload = hidePreloader;
    script.onerror = hidePreloader;
    document.body.appendChild(script);
  }

  function showLoadError() {
    const isFileProtocol = window.location.protocol === 'file:';
    const message = isFileProtocol
      ? 'This section needs the site to be opened through a web server (not by double-clicking the HTML file). ' +
        'Run a local server (e.g. "npx http-server" in this folder) and open the http://localhost link it prints, ' +
        'or visit the live thebrotherhoodlaw.com site.'
      : 'This section could not load its content right now. Please refresh the page.';
    const html = '<div class="content-load-error">' + escapeHtml(message) + '</div>';
    $('.cases-study-slider-area, .slider-images1, .tes2-slider-all1, .articles-row').html(html);
  }

  fetch('assets/data/content.json')
    .then(function (res) {
      if (!res.ok) throw new Error('Failed to load content.json: ' + res.status);
      return res.json();
    })
    .then(function (data) {
      renderGallery(data.gallery || []);
      renderTestimonials(data.testimonials || []);
      renderBlogs(data.blogs || []);
    })
    .catch(function (err) {
      console.error('content-loader: could not load assets/data/content.json', err);
      showLoadError();
    })
    .finally(function () {
      loadMainScript();
    });
})();
