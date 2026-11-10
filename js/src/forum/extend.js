import Extend from 'flarum/common/extenders';
import Post from 'flarum/common/models/Post';
import User from 'flarum/common/models/User';
import { TradeNotification } from './notifications';

export default [
  new Extend.Model(Post).attribute('stickerReactions'),
  new Extend.Model(User).attribute('stickerShowcase'),
  new Extend.Notification().add('stickerTrade', TradeNotification),
];
