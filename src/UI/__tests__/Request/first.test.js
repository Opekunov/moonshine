import request from '../../resources/js/Request/Core.js';
import { ComponentRequestData } from '../../resources/js/DTOs/ComponentRequestData';
import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

// Mock global objects and functions
global.MoonShine = {
  ui: {
    toast: jest.fn(), // Mock the toast function
  },
  callbacks: {},
};

// Mock DOM API
document.querySelectorAll = jest.fn();
document.querySelector = jest.fn();

describe('request function', () => {
  let mockAxios; // For mocking axios requests
  let t;

  beforeEach(() => {
    // Set up axios mock
    mockAxios = new MockAdapter(axios);

    // Reset mocks
    jest.clearAllMocks();

    // Mock component-like object
    t = {
      $el: {},
      loading: true,
    };
  });

  afterEach(() => {
    mockAxios.reset();
  });

  test('should return if url is not provided', () => {
    request(t, '');
    expect(t.loading).toBe(false); // Loading state should not change
    expect(MoonShine.ui.toast).toHaveBeenCalledWith('Request URL not set', 'error');
  });

  test('should display an error if offline', () => {
    jest.spyOn(navigator, 'onLine', 'get').mockReturnValueOnce(false);
    request(t, '/test-url');
    expect(t.loading).toBe(false); // Loading should be set to false
    expect(MoonShine.ui.toast).toHaveBeenCalledWith('No internet connection', 'error');
  });

  test('should instantiate ComponentRequestData if not provided', () => {
    const componentRequestData = null;
    request(t, '/test-url', 'get', {}, {}, componentRequestData);
    expect(MoonShine.ui.toast).not.toHaveBeenCalled(); // No error toast
  });

/*  test('should call beforeRequest if specified', () => {
    const componentRequestData = new ComponentRequestData().withBeforeRequest();
    jest.spyOn(componentRequestData, 'hasBeforeRequest').mockReturnValueOnce(true);
    MoonShine.callbacks.beforeRequest = jest.fn();

    request(t, '/test-url', 'get', {}, {}, componentRequestData);

    expect(componentRequestData.hasBeforeRequest).toHaveBeenCalled();
    expect(MoonShine.callbacks.beforeRequest).toHaveBeenCalledWith(t.$el, t);
  });*/

  test('should handle successful axios response', async () => {
    const componentRequestData = new ComponentRequestData();
    mockAxios.onGet('/test-url').reply(200, { message: 'Success' });

    await request(t, '/test-url', 'get', {}, {}, componentRequestData);

    expect(t.loading).toBe(false); // Loading should be false after response
    expect(MoonShine.ui.toast).toHaveBeenCalledWith('Success', 'success'); // Show success toast
  });

  test('should handle fields_values in response', async () => {
    const componentRequestData = new ComponentRequestData();
    mockAxios.onGet('/test-url').reply(200, {
      fields_values: { '#input': 'value' },
    });

    document.querySelector.mockReturnValueOnce({ value: '', dispatchEvent: jest.fn() });

    await request(t, '/test-url', 'get', {}, {}, componentRequestData);

    expect(document.querySelector).toHaveBeenCalledWith('#input');
  });

  test('should handle redirects in response', async () => {
    delete window.location;
    window.location = { assign: jest.fn() };

    const componentRequestData = new ComponentRequestData();
    mockAxios.onGet('/test-url').reply(200, { redirect: '/new-location' });

    await request(t, '/test-url', 'get', {}, {}, componentRequestData);

    expect(window.location.assign).toHaveBeenCalledWith('/new-location');
  });

  test('should handle attachments in response', async () => {
    mockAxios.onGet('/test-url').reply(200, 'File content', {
      'content-disposition': 'attachment; filename="file.txt"',
    });

    const createElementSpy = jest.spyOn(document, 'createElement').mockReturnValue({
      click: jest.fn(),
      style: {},
    });

    await request(t, '/test-url');

    expect(createElementSpy).toHaveBeenCalledWith('a');
  });

  test('should handle errors in axios response', async () => {
    const componentRequestData = new ComponentRequestData();
    mockAxios.onGet('/test-url').reply(500, { message: 'Error' });

    await request(t, '/test-url', 'get', {}, {}, componentRequestData);

    expect(t.loading).toBe(false);
    expect(MoonShine.ui.toast).toHaveBeenCalledWith('Error', 'error');
  });

  test('should display "Unknown Error" if no error message is present', async () => {
    mockAxios.onGet('/test-url').networkError();

    await request(t, '/test-url');

    expect(MoonShine.ui.toast).toHaveBeenCalledWith('Unknown Error', 'error');
  });
});
