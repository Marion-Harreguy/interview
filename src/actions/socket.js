export const CONNECT_WEBSOCKET = 'CONNECT_WEBSOCKET';
export const UPDATE_USER = 'UPDATE_USER';

// ALL COMPONENTS : Updating user
export const connectWebsocket = (payload) => ({
  type: CONNECT_WEBSOCKET,
  payload,
});

// REMETTRE PAYLOAD
export const updateUser = (payload) => ({
  type: UPDATE_USER,
  payload
});