import selectorsParams from '../Support/SelectorsParams.js'
import {ComponentRequestData} from '../DTOs/ComponentRequestData.js'
import request, {afterResponse, beforeRequest, initCallback} from '../Request/Core.js'
import {getQueryString} from '../Support/Forms.js'

export default (asyncUpdateRoute = '') => ({
  asyncUpdateRoute: asyncUpdateRoute,
  withParams: '',
  loading: false,

  init() {
    this.loading = false
    this.withParams = this.$el?.dataset?.asyncWithParams
  },
  async fragmentUpdate(events, callback = {}) {
    if (this.asyncUpdateRoute === '') {
      return
    }

    if (this.loading) {
      return
    }

    callback = initCallback(callback)

    this.loading = true

    let body = selectorsParams(this.withParams)

    const t = this

    const query = new URLSearchParams(body).toString()

    t.asyncUpdateRoute += t.asyncUpdateRoute.includes('?') ? '&' + query : '?' + query
    t.asyncUpdateRoute += getQueryString(this.$event.detail)

    let stopLoading = function (data, t) {
      t.loading = false
    }

    let componentRequestData = new ComponentRequestData()
    componentRequestData
      .withEvents(events)
      .withBeforeRequest(callback.beforeRequest)
      .withBeforeHandleResponse(stopLoading)
      .withResponseHandler(callback.responseHandler)
      .withAfterResponse(function (data) {
        t.$root.outerHTML = data

        if (callback.afterResponse) {
          afterResponse(callback.afterResponse, data)
        }
      })
      .withErrorCallback(stopLoading)

    request(t, t.asyncUpdateRoute, 'get', body, {}, componentRequestData)
  },
})