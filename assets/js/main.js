(function ($) {
  $(document).ready(function () {

// MOBILE-MENU ACTIVE //
if ($("#mobile-menu").length > 0) {
  $("#mobile-menu").meanmenu({
    meanMenuContainer: ".mobile-menu",
    meanScreenWidth: "991",
  });
  $(".mobile-menu-bar").on("click", function () {
    $(".sidebar-main").addClass("active");
  });
  $(".sidebar-close").on("click", function () {
    $(".sidebar-main").removeClass("active");
  });
}
// VIDEO POPUP //
if ($(".play-btn").length > 0) {
  $(".play-btn").magnificPopup({
    type: "iframe",
  });
}
// COUNTER AREA //
$(".counter").countUp();
  if ($("#header").length > 0) {
    $(window).on("scroll", function (event) {
      var scroll = $(window).scrollTop();
      if (scroll < 1) {
        $("#header").removeClass("sticky");
      } else {
        $("#header").addClass("sticky");
      }
    });
  }
});

// TESTIMONIAL AREA //
$(".tes1-slider").owlCarousel({
  loop: true,
  margin: 10,
  nav: false,
  dots: true,
  smartSpeed: 1000,
  slideSpeed: 600,
  responsive: {
    0: {
      items: 1,
    },
    900: {
      items: 3,
    },
  },
});

// IMAGES ANIMATION //
$(".homepage-slides").on("translate.owl.carousel", function () {
$(".single-slide-item h3")
      .removeClass("animated fadeInUp")
      .css("opacity", "1");
  $(".single-slide-item h1")
      .removeClass("animated fadeInDown")
      .css("opacity", "1");        
  $(".single-slide-item p")
      .removeClass("animated fadeInUp")
      .css("opacity", "1");
  $(".single-slide-item .main-btn")
      .removeClass("animated fadeInDown")
      .css("opacity", "1");
});

$(".homepage-slides").on("translated.owl.carousel", function () {
$(".single-slide-item h3")
      .addClass("animated fadeInUp")
      .css("opacity", "1");
  $(".single-slide-item h1")
      .addClass("animated fadeInDown")
      .css("opacity", "1");        
  $(".single-slide-item p")
      .addClass("animated fadeInUp")
      .css("opacity", "1");
  $(".single-slide-item .main-btn")
      .addClass("animated fadeInDown")
      .css("opacity", "1");
});

$('.cs_hover_active').hover(function () {
$(this).addClass('active').siblings().removeClass('active');
});
    
// POPUP VIDEO //
  $('.popup-youtube').magnificPopup({
    type: 'iframe'
  });

// TESTIMONIAL AREA //
$(".tes2-slider-all").slick({
  slidesToShow: 1,
  slidesToScroll: 1,
  asNavFor: ".slider-images",
  dots: true,
  arrows: false,
  centerMode: false,
  focusOnSelect: true,
  loop: false,
});

$(".slider-images").slick({
  slidesToShow: 1,
  slidesToScroll: 1,
  arrows: false,
  loop: false,
  focusOnSelect: true,
  autoplay: true,
  autoplaySpeed: 3000,
  fade: true,
  asNavFor: ".tes2-slider-all",
  
});

// PAGE PROGRESS AREA //
var progressPath = document.querySelector(".progress-wrap path");
var pathLength = progressPath.getTotalLength();
progressPath.style.transition = progressPath.style.WebkitTransition =
  "none";
progressPath.style.strokeDasharray = pathLength + " " + pathLength;
progressPath.style.strokeDashoffset = pathLength;
progressPath.getBoundingClientRect();
progressPath.style.transition = progressPath.style.WebkitTransition =
  "stroke-dashoffset 10ms linear";
var updateProgress = function () {
  var scroll = $(window).scrollTop();
  var height = $(document).height() - $(window).height();
  var progress = pathLength - (scroll * pathLength) / height;
  progressPath.style.strokeDashoffset = progress;
};
updateProgress();
$(window).scroll(updateProgress);
var offset = 50;
var duration = 550;
jQuery(window).on("scroll", function () {
  if (jQuery(this).scrollTop() > offset) {
    jQuery(".progress-wrap").addClass("active-progress");
  } else {
    jQuery(".progress-wrap").removeClass("active-progress");
  }
});
jQuery(".progress-wrap").on("click", function (event) {
  event.preventDefault();
  jQuery("html, body").animate({ scrollTop: 0 }, duration);
  return false;
});

// AOS ANIMATION //
AOS.init({
  offset: 100,
  duration: 50,
  easing: "ease-in-out",
  anchorPlacement: "top-bottom",
  disable: "mobile",
});
  
// TESTIMONIAL AREA //
$('.testimonial4-slider-area').owlCarousel({
  loop:true,
  margin:30,
  nav:true,
  dots:false,
  navText:["<i class='fa-solid fa-angle-left'></i>" , "<i class='fa-solid fa-angle-right'></i>"],
  items:10,
  autoplay:true,
  smartSpeed:2000,
  autoplayTimeout:4000,
  responsiveClass:true,
  responsive:{
      0:{
          items:1,
          nav:true,
      },
      600:{
          items:1,
      },
      1000:{
          items:1,
      }
  }
}); 


// TESTIMONIAL AREA //
$('.rtl-carousel').owlCarousel({
  loop:true,
  margin:30,
  nav:true,
  dots:false,
  navText:["<i class='fa-solid fa-angle-left'></i>" , "<i class='fa-solid fa-angle-right'></i>"],
  items:10,
  autoplay:true,
  rtl:true,
  smartSpeed:2000,
  autoplayTimeout:4000,
  responsiveClass:true,
  responsive:{
      0:{
          items:1,
          nav:true,
      },
      600:{
          items:1,
      },
      1000:{
          items:1,
      }
  }
}); 
// TESTIMONIAL AREA //
$('.testimonial6-carousel-area').owlCarousel({
  loop:true,
  margin:30,
  nav:true,
  dots:false,
  navText:["<i class='fa-solid fa-arrow-left'></i>" , "<i class='fa-solid fa-arrow-right'></i>"],
  items:10,
  autoplay:true,
  smartSpeed:2000,
  autoplayTimeout:4000,
  responsiveClass:true,
  responsive:{
      0:{
          items:1,
          nav:true,
      },
      600:{
          items:2,
      },
      1000:{
          items:2,
      }
  }
});

// TESTIMONIAL AREA //
$('.cases-study-slider-area').owlCarousel({
  loop:true,
  margin:30,
  nav:false,
  dots:false,
  items:10,
  autoplay:true,
  smartSpeed:2000,
  autoplayTimeout:4000,
  responsiveClass:true,
  responsive:{
      0:{
          items:1,
      },
      600:{
          items:2,
      },
      1000:{
          items:3,
      }
  }
});

// TESTIMONIAL AREA //
$('.case-study-carousel').owlCarousel({
  loop:true,
  margin:30,
  nav:false,
  dots:false,
  items:10,
  autoplay:true,
  smartSpeed:2000,
  autoplayTimeout:4000,
  responsiveClass:true,
  responsive:{
      0:{
          items:1,
      },
      600:{
          items:2,
      },
      1000:{
          items:3,
      }
  }
});
     
// TESTIMONIAL AREA //
$('.slider-images1').slick({
  slidesToShow: 1,
  slidesToScroll: 1,
  items:10,
  arrows: true,
  fade:true,
  autoplay:true,
  smartSpeed:2000,
  autoplayTimeout:4000,
  infinite: true,
  asNavFor: '.tes2-slider-all1',
  prevArrow: $('.testimonial-next-arrow'),
  nextArrow: $('.testimonial-prev-arrow'),
});
$('.tes2-slider-all1').slick({
  slidesToShow: 1,
  slidesToScroll: 1,
  items:15,
  fade:true,
  arrows: true,
  autoplay:true,
  smartSpeed:2000,
  autoplayTimeout:4000,
  asNavFor: '.slider-images1',
  vertical: false,
  verticalSwiping: false,
  focusOnSelect: true,
  infinite: true,
  prevArrow: $('.testimonial-next-arrow'),
  nextArrow: $('.testimonial-prev-arrow'),
});

// MODAL VIDEO //
$(".headerbtn").modalVideo();

// PRELOADER AREA //
$(window).on("load", function (event) {
  setTimeout(function () {
    $("#preloader").fadeToggle();
  }, 1000);  
});
})(jQuery);






    