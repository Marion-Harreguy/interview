import { combineReducers } from 'redux';
import newUser from './newUser';
import forgottenPassword from './forgottenPassword';
import login from './login';
import userData from './userData';

export default combineReducers({
  newUser,
  forgottenPassword,
  login,
  userData,
});