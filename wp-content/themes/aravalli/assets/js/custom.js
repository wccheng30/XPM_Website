(function ($) {
    "use strict";

        // ScrollUp
        $(window).on('scroll', function () {
            if ($(this).scrollTop() > 100) {
                $('.scrollup').fadeIn();
            } else {
                $('.scrollup').fadeOut();
            }
        });

        $('.scrollup').on('click', function () {
            $("html, body").animate({
                scrollTop: 0
            }, 600);
            return false;
        });

        // Sticky Menu
        $(window).scroll(function() {
            if ($(window).scrollTop() >= 250) {
                $('.sticky-nav').addClass('sticky-menu');
            }
            else {
                $('.sticky-nav').removeClass('sticky-menu');
            }
        });

        $('.menubar .menu-wrap > li').hover(
        function(){
            $("li.active").addClass('inactive').removeClass('active');
        },
        function(){
            $("li.inactive").addClass('active').removeClass('inactive'); 
        });

        // Add/Remove .focus class for accessibility
        $('.menubar').find('a').on('focus blur', function() {
            $( this ).parents('ul, li').toggleClass('focus');
        });

        // Search Pop Up
        $(document).on('click','.view-popup', function(e){
            var btnId = $(this).attr('id');
            $( "body" ).addClass( "overlay-enabled" );
            $('.view-search').fadeIn(500);
            $( "."+ btnId ).addClass( 'on' );
            if ($('.view-search').hasClass('on')) {
                $('.form-control.search-field').focus();
                var links,i,len,menuItem=document.querySelector('.view-search-btn'),fieldToggle=document.querySelector('.form-control.search-field');let focusableElements='button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';let firstFocusableElement=fieldToggle;let focusableContent=menuItem.querySelectorAll(focusableElements);let lastFocusableElement=focusableContent[focusableContent.length-1];if(!menuItem){return!1}
                links=menuItem.getElementsByTagName('button');for(i=0,len=links.length;i<len;i++){links[i].addEventListener('focus',toggleFocus,!0);links[i].addEventListener('blur',toggleFocus,!0)}
                function toggleFocus(){var self=this;while(-1===self.className.indexOf('view-search-form')){if('input'===self.tagName.toLowerCase()){if(-1!==self.className.indexOf('focus')){self.className=self.className.replace('focus','')}else{self.className+=' focus'}}
                self=self.parentElement}}
                document.addEventListener('keydown',function(e){let isTabPressed=e.key==='Tab'||e.keyCode===9;if(!isTabPressed){return}
                if(e.shiftKey){if(document.activeElement===firstFocusableElement){lastFocusableElement.focus();e.preventDefault()}}else{if(document.activeElement===lastFocusableElement){firstFocusableElement.focus();e.preventDefault()}}});
            }
        });

        $(document).on('click','.view-search-remove', function(e){
            $( "body" ).removeClass( "overlay-enabled" );
            $('.view-search').fadeOut(500);
            $( ".view-search" ).removeClass('on');
            if (!$('.view-search').hasClass('on')) {
                $('.view-popup').focus();
            }
              return this;
        });

        // Mobile Menu
        $(".menubar .menu-wrap")
        .clone()
        .appendTo(".mobile-menus");

        var $mob_menu = $("#mobile-m");
        $(".close-menu").on("click", function() {
          $mob_menu.toggleClass("menu-show");
          $( "body" ).removeClass( "overlay-enabled" );
          if (!$mob_menu.hasClass('menu-show')) {
                $(".menutogglebtn").focus();
          }
        });

        $(".menutogglebtn").on("click", function() {
            if (!$mob_menu.hasClass('menu-show')) {
                $mob_menu.addClass("menu-show");
                $( "body" ).addClass( "overlay-enabled" );
                $('.close-menu').focus();
                var links,i,len,menuItem=document.querySelector('.mobile-menu'),fieldToggle=document.querySelector('.close-menu');let focusableElements='button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';let firstFocusableElement=fieldToggle;let focusableContent=menuItem.querySelectorAll(focusableElements);let lastFocusableElement=focusableContent[focusableContent.length-1];if(!menuItem){return!1}
                links=menuItem.getElementsByTagName('button');for(i=0,len=links.length;i<len;i++){links[i].addEventListener('focus',toggleFocus,!0);links[i].addEventListener('blur',toggleFocus,!0)}
                function toggleFocus(){var self=this;while(-1===self.className.indexOf('mobile-menus')){if('li'===self.tagName.toLowerCase()){if(-1!==self.className.indexOf('focus')){self.className=self.className.replace('focus','')}else{self.className+=' focus'}}
                self=self.parentElement}}
                document.addEventListener('keydown',function(e){let isTabPressed=e.key==='Tab'||e.keyCode===9;if(!isTabPressed){return}
                if(e.shiftKey){if(document.activeElement===firstFocusableElement){lastFocusableElement.focus();e.preventDefault()}}else{if(document.activeElement===lastFocusableElement){firstFocusableElement.focus();e.preventDefault()}}});
            }
        });
        $(".mobi_drop").on("click", function(e) {
            e.preventDefault();
            $(this)
              .parent()
              .toggleClass("current");
            $(this)
              .next()
              .slideToggle();
        });

        $(".header-widget")
        .clone()
        .appendTo(".mobi-head-top");
        var $mob_h_top = $("#mob-h-top");

        $('.header-sidebar-toggle').on('click', function(e) {
          $mob_h_top.toggleClass("active"); //you can list several class names 
          $('.header-sidebar-toggle').toggleClass("active");      
          e.preventDefault();
        });

        $(".menu-right")
        .clone()
        .appendTo(".mobi-head-cart");
		
		// Main Slider
        $(".main-slider").owlCarousel({
            items: 1,
            loop: true,
            dots: true,
            nav: true,            
            navText: ['<i class="fa fa-angle-left"></i> <span>Slide</span>', '<i class="fa fa-angle-right"></i> <span>Slide</span>'],
            autoplay: true,
            smartSpeed: 1000,
			autoplayTimeout: 5000,
        });
        // Header Slide items with animate.css
        var owlMain = $('.main-slider');
        owlMain.owlCarousel();
        owlMain.on('translate.owl.carousel', function (event) {
            var data_anim = $("[data-animation]");
            data_anim.each(function() {
                var anim_name = $(this).data('animation');
                $(this).removeClass('animated ' + anim_name).css('opacity', '0');
            });
        });
        $("[data-delay]").each(function() {
            var anim_del = $(this).data('delay');
            $(this).css('animation-delay', anim_del);
        });
        $("[data-duration]").each(function() {
            var anim_dur = $(this).data('duration');
            $(this).css('animation-duration', anim_dur);
        });
        owlMain.on('translated.owl.carousel', function() {
            var data_anim = owlMain.find('.owl-item.active').find("[data-animation]");
            data_anim.each(function() {
                var anim_name = $(this).data('animation');
                $(this).addClass('animated ' + anim_name).css('opacity', '1');
            });
        });
        function owlMainThumb() {
            $('.owl-item').removeClass('prev next');
            var currentSlide = $('.main-slider .owl-item.active');
            currentSlide.next('.owl-item').addClass('next');
            currentSlide.prev('.owl-item').addClass('prev');
            var nextSlideImg = $('.owl-item.next').find('.item img').attr('data-img-url');
            var prevSlideImg = $('.owl-item.prev').find('.item img').attr('data-img-url');
            $('.owl-nav .owl-prev').css({
                backgroundImage: 'url(' + prevSlideImg + ')'
            });
            $('.owl-nav .owl-next').css({
                backgroundImage: 'url(' + nextSlideImg + ')'
            });
        }
        owlMainThumb();
        owlMain.on('translated.owl.carousel', function() {
            owlMainThumb();
        });
}(jQuery));

