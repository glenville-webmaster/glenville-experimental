/**
 * @file
 *
 * All global scripts for OpenEDU theme
 */
(function ($, window, Drupal) {

  'use strict';

  // Match Heights
  Drupal.behaviors.glenvilleMatchHeight = {
    attach: function (context, settings) {
      matchAllHeight(context);
    }
  };

  Drupal.behaviors.glenvilleAlertModals = {
    attach: function (context, settings) {
      var alert_modals = $('.alert-modal', context);

      $(window).on('load',function() {
        if (alert_modals.length) {
          alert_modals.modal('show');
        }
      });
    }
  };

  Drupal.behaviors.glenvilleMoveLocalTasks = {
    attach: function (context, settings) {
      var menu_tasks = $('.local-menu-tasks', context);

      $(document).ready(function() {
        if (menu_tasks.length) {
          if($('.homepage-statistics').length) {
            menu_tasks.insertAfter($('.homepage-statistics'));
          } else if ($('.panel-top').length) {
            menu_tasks.insertAfter($('.panel-top'));
          } else if ($('.block-glenville-tab-block').length) {
            menu_tasks.insertBefore($('.block-glenville-tab-block'));
          } else if ($('.breadcrumb-wrapper').length) {
            menu_tasks.insertAfter($('.breadcrumb-wrapper'));
          } else if ($('.band-hero').length) {
            menu_tasks.insertAfter($('.band-hero'));
          } else if ($('.path-user').length) {
            menu_tasks.parent().removeClass('d-none');
          } else {
            menu_tasks.parent().removeClass('d-none').addClass('py-5');
          }
        }
      });

    }
  };

  Drupal.behaviors.glenvilleSearchOverlay = {
    attach: function (context, settings) {
      var searchButton = $('.header__bar--search_btn', context);

      if (searchButton.length) {
        searchButton.once('gv-search').on('click', function(e) {
          $('.overlay, .search_bar').toggleClass('show');
          e.preventDefault();
        });

        $(window).resize(function() {
          $('.form-autocomplete').autocomplete('close');
        });
      }
    }
  };

  Drupal.behaviors.glenvilleTabBlock = {
    attach: function (context, settings) {
      var $tabBlock = $('.glenville-tabs', context);

      if ($tabBlock.length) {

        // Sticky tabs.
        if ($tabBlock.hasClass('sticky')) {
          var tabTopHeight = $tabBlock.outerHeight();
          var tabTop = $tabBlock.offset().top;
          $(window).scroll(function () {
            if (window.pageYOffset >= (tabTop - tabTopHeight)) {
              if (!$tabBlock.hasClass('stuck')) {
                $tabBlock.addClass('stuck');
                $tabBlock.css({'top': $('body').css('padding-top')});
              }
            }
            else {
              $tabBlock.removeClass('stuck');
            }
          });
        }

        // Click tabs.
        $tabBlock.find('a').each(function(){
          var $this = $(this);
          var $target = $('.' + $this.attr('data-tab-class'));

          if ($target.length && !$target.children().is(':empty')) {
            $this.click(function(e){
              window.scroll({top: $target[0].offsetTop + 20, behavior: 'smooth'});
              e.preventDefault();
            });
          }
          // Remove non-existing tabs.
          else {
            $(this).parent().remove();
          }
        });

      }
    }
  };

  Drupal.behaviors.glenvilleAccordions = {
    attach: function (context, settings) {
      var accordion = $('.accordion', context);
      if (accordion.length) {
        accordion.find('.card-header').once('gv-accordions').on('click', function() {
          $(this).toggleClass('active').siblings('.collapse').collapse('toggle');
        });
      }
    }
  };

  Drupal.behaviors.glenvilleMegaMenuPlacement = {
    attach: function (context, settings) {
      $('.main_navigation-block--desktop .mega-menu-wrapper').once('gv-megamenu-positioning').each(function() {
        var _this = $(this);

        // set our width classes
        var regionCount = _this.find('.megamenu-column:not(.megamenu-top)').length;
        var regionLinksCount = _this.find('.paragraph--type--link-column').length;
        _this.addClass('columns-' + regionCount);
        _this.addClass('link-columns-' + regionLinksCount);

        var megaWidth = _this.outerWidth();

        $(window).on("load resize", function () {
          calcPos(megaWidth, _this);
        });

        var calcPos = function (megaWidth, _this) {
           var megaPos = ($('body').width() - (_this.offset().left + megaWidth));
           redrawMenu(megaPos, _this);
        };

        // add our alignment class if the menu falls outside of the window
        var redrawMenu = function (megaPos, _this) {
          if (megaPos > 0) {
            if(_this.hasClass('megamenu-align-right')) {
               _this.removeClass('megamenu-align-right');
            } else {
               _this.addClass('megamenu-align-left');
            }
          } else {
            _this.removeClass('megamenu-align-left').addClass('megamenu-align-right');
          }
        };

      });
    }
  };

  Drupal.behaviors.glenvillePushMenu = {
    attach: function (context, settings) {

      // init the push menu
      new mlPushMenu(document.getElementById('mp-menu'), document.getElementById('header__bar--menu_btn'), {
        type: 'cover',
      });

      // collapse items on mobile
      var mobileLinkSection = $('.main_navigation-block--mobile .menu-link-section', context);
      if (mobileLinkSection.length) {
          mobileLinkSection.once('gv-menu-link-section').each(function(e) {
          var mobileLinkHeading = $(this).find('h4');

          if(mobileLinkHeading.length) {

            // make submenus collapsible
            $(this).find('.list-unstyled').addClass('mt-0 collapse');

            // change icon when clicked
            toggleIcon(mobileLinkHeading, context);

            // expand/collapse
            mobileLinkHeading.once('gv-submenu-toggle').on('click', function(e) {
              var mobileLinkLists = $(this).parent().find('.list-unstyled');
              mobileLinkLists.collapse('toggle');
            });

          }
        });
      }

      // kill the menu if opened and window is larger than 991px
      $(window).bind("load resize", function() {
        var width = $(window).width();
        if($('.mp-pushed').length) {
          if (width >= 991) {
            $("#header__bar--menu_btn").get(0).click();
          }
        }
      });

    }
  };

  Drupal.behaviors.glenvilleHeroVideo = {
    attach: function (context, settings) {
      var $heroWithVideo = $('.block--openedu-hero.has-video', context);
      if ($heroWithVideo.length) {
        var $video = $heroWithVideo.find('video');
        var $control = $heroWithVideo.find('.video-upload-control');

        if ($video.length) {
          var video = $video.get(0);

          // Start it up.
          video.play();

          $heroWithVideo.find('.pause, .play').click(function(e) {
            if (video.paused === false) {
              $control.addClass('paused');
              video.pause();
            }
            else {
              $control.removeClass('paused');
              video.play();
            }
          });

        }
      }
    }
  };

  Drupal.behaviors.glenvilleAlerts = {
    attach: function (context, settings) {

      // Only ever call this once.
      $('#glenville-alert', context).once('check-alerts').each(function () {
        var $container = $('#glenville-alert', context);
        var $alert_content = $container.find('.alert-content');
        if ($alert_content.length) {
          // Get uncached.
          var timestamp = new Date().getTime();

          $.get(
            Drupal.url('jsonapi/node/alert?filter[status][value]=1'),
            function (response) {
              if (response.data.length) {
                $.each(response.data, function (idx, el) {
                  $alert_content.append(
                    '<div class="alert-item">' +
                    '<div class="alert-title">' + el.attributes.title + '</div>' +
                    '<div class="alert-body">' + el.attributes.body.processed + '</div>' +
                    '</div>'
                  );
                });
                $container.slideDown();
              }
            }
          );
        }
      });
    }
  };


  Drupal.behaviors.GlenvilleNewsEvents = {
    attach: function (context, settings) {
      // Removes featured news when search has been performed
      if ($('.phoenix-news-changed', context).length) {
        $('.block-views-blockphoenix-news-featured-news-block').addClass('hidden');
      }

      // Removes featured events when search has been performed
      if ($('.events-changed', context).length) {
        $('.block-views-blockevent-featured-events-block').addClass('hidden');
      }
    }
  };

  Drupal.behaviors.GlenvilleDirectory = {
    attach: function (context, settings) {
      // Removes featured news when search has been performed
      if ($('.directory-changed', context).length) {
        $('.block-views-blockprofile-list-profile-listing-block .directory-attached').addClass('hidden');
      }
    }
  };

  /**
   * SHARED FUNCTIONS
   */

  var toggleIcon = function (elem, context) {
    var $togglerIcon = $(elem, context);
    if ($togglerIcon.length) {
      $togglerIcon.click(function(e) {
        e.preventDefault();
        var el = $(this).find('i');
        if (el.text() === el.data("text-alter")) {
          el.text(el.data("text-original"));
        } else {
          el.data("text-original", el.text());
          el.text(el.data("text-alter"));
        }
      });
    }
  };

  function matchAllHeight(context) {
    var el = [
      // '.container-wide .row > div',
      '.field--name-field-grid-content .embedded-entity',
      '.featured .card-header',
      '.card-img-overlay',
      '.underlay-black .card-title',
      '.card-footer'
    ];

    // make them all equal heights.
    $.each(el, function (index, value) {
      $(value, context).matchHeight();
    });
  }

  /**
   * Watch the body for attribute changes.
   *
   * This is to detect padding changes from the admin toolbar
   * or other tools that may manipulate the top position
   * of the dashboard.
   */
  var observerConfig = {
    attributes: true,
    subtree: false,
    childList: false,
    characterData: false
  };
  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.attributeName === "style") {
        updateBodyPadding();
      }
    });
  });
  observer.observe(document.getElementsByTagName("BODY")[0], observerConfig);

  /**
   * Helper for when the admin toolbar is enabled.
   */
  function updateBodyPadding() {
    var $bodyPadding = $('body').css('padding-top');

    // Sticky tabs.
    $('.glenville-tabs.stuck').css({'top': $bodyPadding});

  }

})(jQuery, window, Drupal);
