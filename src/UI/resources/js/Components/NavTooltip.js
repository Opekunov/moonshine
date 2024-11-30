import tippy from 'tippy.js'

export default () => ({
  tooltipInstance: null,
  horizontalMenuEl: document.querySelector('.layout-menu-horizontal'),

  init() {
    if (this.horizontalMenuEl && this.horizontalMenuEl.contains(this.$el)) {
      return
    }

    this.tooltipInstance = tippy(this.$el, {
      placement: 'auto',
      offset: [0, 30],
      trigger: 'mouseenter',
      content: () => this.$el.querySelector('.menu-inner-text').textContent,
    })
  },

  toggleTooltip() {
    if (this.horizontalMenuEl && this.horizontalMenuEl.contains(this.$el)) {
      return
    }

    const lgMediaQuery = window.matchMedia('(min-width: 1024px) and (max-width: 1279.98px)')

    if (!this.$data.minimizedMenu && !lgMediaQuery.matches) {
      this.tooltipInstance.hide()
    }
  },
})