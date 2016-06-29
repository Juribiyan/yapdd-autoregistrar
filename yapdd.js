var conf = {
  boxesPerUser: 3,
  domain: '0chan.one'
}
if(!conf.hasOwnProperty('domain'))
  conf.domain = document.location.hostname

function main() {
  builder.init();

  var uid = getCookie('yapdd_autoreg_id');
  if(uid.length === 32) 
    api.view(uid)
  else {
    api.view(uid)
    setClassFromBoxCount(0)
  }

  $('form').on('submit', function(ev) {
    ev.preventDefault()
    var spoiled = false, data = {};
    $(this).find('input[type=text], input[type=password], input[type=hidden]').each(function() {
      var invalid = !validateInput(this)
      if(invalid) {
        $(this).addClass('invalid')
        spoiled = true
      }
      data[this.name] = this.value
    })
    if(!spoiled) {
      api[$(this).data('act')].bind(api)(data)
    }
  })

  $('input[type=text], input[type=password]').on('input', function() {
    $(this).toggleClass('invalid', !validateInput(this))
    $form = $(this).parents('form')
    $form.toggleClass('invalid', !!$form.find('input.invalid').length)
  })

  $('.xlink').click(function(ev){
    actions[$(this).data('act')]()
  })

  $('.addbtn').click(function() {
    $('#addbox').addClass('expanded')
  })

  $('body').on('click', '.delete-mailbox', function(ev) {
    ev.stopPropagation()
    var $flo = $('#delbox-floater');
    $flo.find('input[name=login]').val($(this).parents('.mailbox').data('login'))
    $flo.find('input[type=password]')[0].setAttribute('readonly', true)
    $(this).append($flo.fadeIn(0.1, function() {
      $flo.find('input[type=password]').focus()
    }))
  })
  $(window).on('click', function() {
    $('#delbox-floater').hide()
    $('.popup:last').slideOff('fast')
  })

  $('#delbox-floater').click(function(ev) {
    ev.stopPropagation()
  })

  $('.captcha').click(function() {
    updateCaptcha()
    $('input[name=captcha]').focus()
  })

  $(window).resize(fitDelbox).trigger('resize')
}

function validateInput(input) {
  var $input = $(input)
  , value = input.value
  , $form = $input.parents('form')

  if($input.data('equal')) {
    var $bro = $form.find('input[name='+$input.data('equal')+']')
    if(!$bro.length) {
      console.warn('Paired element not found')
      return true
    }
    if($bro.val() !== value) {
      $bro.addClass('invalid')
      return false
    }
  }

  if($input.data('nonequal')) {
    var $foe = $form.find('input[name='+$input.data('nonequal')+']')
    if(!$foe.length) {
      console.warn('Paired element not found')
      return true
    }
    if($foe.val() === value) {
      $foe.addClass('invalid')
      return false
    }
    else $foe.removeClass('invalid')
  }

  if(value === '') 
    return (!input.required)
  

  if(input.pattern) {
    try {
      var rx = new RegExp(input.pattern)
      if(!rx.test(value))
        return false
    }
    catch(e) {
      console.error('Invalid Pattern RegExp.', e)
    }
  }

  if($input.data('specialcase') && !specialValidations[$input.data('specialcase')](value))
    return false

  if($input.data('equal'))
    $bro.removeClass('invalid')

  return true
}

var specialValidations = {
  boxNotInList: function(value) {
    $('.mailbox').removeClass('invalid')
    var $existingBox = $('.mailbox[data-login='+value+']')
    if($existingBox.length) {
      $existingBox.addClass('invalid')
      return false
    }
    return true
  }
}


var actions = {
  login: function() {
    $('#login-form').slideToggle('fast')
  }
}

var api = {
  view: function(uid) {
    this.request('view', {
      uid: uid
    }, builder.list.bind(builder))
  },
  login: function(data) {
    data.do_login = 1
    this.request('view', data, builder.list.bind(builder))
  },
  add: function(data) {
    this.request('add', data, function(res) {
      $('#addbox input[type!=submit]').val('')
      builder.addBox.bind(builder)(res)
    })
  },
  delete: function(data) {
    this.request('delete', data, builder.removeBoxByName.bind(builder))
  },
  request: function(action, data, callback) {
    $('body').addClass('xhring')
    $.post('api.php?action='+action, data, function(res) {
      $('body').removeClass('xhring')
      updateCaptcha()
      if(res.error)
        popup(res.error)
      else
        callback(res.data)
    }, 'json')
    .fail(function() {
      popup('XHR error')
    })
  }
}

$.fn.slideOff = function(speed) {
  this.slideUp(speed, function() {
    this.remove()
  })
}

function updateCaptcha() {
  var src = $('.captcha').attr('src').split('&')[0]
  $('.captcha').attr('src', src+'&'+Math.random())
  $('input[name=captcha]').val('')
}

function popup(msg) {
  msg = errMap[msg] || msg
  if(typeof msg === 'object') {
    var options = msg;
    msg = msg.msg
  }
  else options = {}
  
  var $existing = $('.popup')

  $('<div class="popup '+(options.msgClass || 'error')+'-pup"><div class="p-msg">'+msg+'</div></div>')
  .appendTo('#popup-container')
  .slideDown('fast', function() {
    $existing.slideOff('fast')
    if(!options.keep)
      setTimeout((function(){
        $(this).slideOff('fast')
      }).bind(this), 2000)
  })
}

var errMap = {
  get occupied() {
    $('#register-form input[name=login]').select().addClass('invalid').focus()
    return "Имя занято."
  },
  get 'wrong-captcha'() {
    $('input[name=captcha]').focus()
    return 'Капча введена неверно.'
  },
  'fill-form': "Заполните форму",
  'passwords-different': 'Пароли не совпадают',
  'password=login': "Логин и пароль не должны совпадать",
  get 'invalid-login'() {
    $('#addbox input[name=login]').select().addClass('invalid').focus()
    return 'Логин содержит запрещенные символы или слишком длинный'
  },
  get 'banned-name'() {
    $('#addbox input[name=login]').select().addClass('invalid').focus()
    return "Это имя запрещено к использованию"
  },
  get 'wrong-password'() {
    $('#delbox-floater input[name=password]').val('').addClass('invalid').focus()
    return "Неверный пароль"
  },
  get 'must-login'() {
    $('#login-form').slideDown('fast')
    $('body').addClass('must-login')
    return {
      keep: true,
      msgClass: 'warn',
      msg: 'С вашего IP-адреса уже были зарегистрированы ящики. Войдите, чтобы начать работу с ящиками.'
    }
  },
  get 'max-mailbox-count-reached'() {
    $('body').addClass('boxlimit')
    return "Вы не можете создавать больше ящиков."
  },
  'wrong-ip': "IP определен неверно",
  'no-action': "Неверный запрос (no action field)",
  'occupied-by-you': "Вы уже зарегистрировали ящик с таким именем",
  'unregistered': "Вы не зарегистрированы",
  'api-error': "Ошибка доступа к API",
  'not-found': 'Аккаунт не найден',
  'curl-error': 'Ошибка cURL',
  'mysql_error': 'Ошибка базы данных',
  // Ошибки, описанные в API Яндекса. Если они возникают, вы делаете что-то не так.
  'no_token': 'Не передан токен',
  'bad_token': 'Передан неверный токен',
  'prohibited': 'Запрещенное имя домена',
  'no_domain': 'Не передан домен',
  'bad_domain': 'Имя домена не указано или не соответствует RFC',
  'blocked': 'Домен заблокирован',
  'domain_limit_reached': 'Превышено допустимое количество подключенных доменов (50)',
  'no_reply': 'Яндекс.Почта для домена не может установить соединение с сервером-источником для импорта (что бы это ни значило)',
  'unknown': 'Произошел временный сбой или ошибка работы API (повторите запрос позже)',
  'no_auth': 'Не передан заголовок PddToken'
}

function setClassFromBoxCount(count) {
  fitDelbox() //booo side effects boooo
  if(typeof count === 'undefined')
    count = $('.mailbox').length
  $('body').removeClass('no-boxes boxlimit');
  if(count === 0) 
    $('body').addClass('no-boxes')
  if(count >= conf.boxesPerUser)
    $('body').addClass('boxlimit')
}

var builder = {
  init: function() {
    this.$list = $('#boxlist')
  },
  list: function(list) {
    $('body').removeClass('must-login') //booo side effects boooo
    setClassFromBoxCount(list.length)
    if(!list.length) 
      return
    var html = '';
    list.forEach(this.addBox.bind(this))
  },
  box: function(login) {
    return '<li class="mailbox" data-login="'+login+'">'+
      '<div class="mailbox-name">'+login+'@'+conf.domain+'</div>\
      <div class="delete-mailbox" title="Удалить">×</div>\
    </li>'
  },
  addBox: function(login) {
    this.$list.append($(this.box(login)))
    setClassFromBoxCount()
  },
  removeBoxByName: function(login) {
    $('#delbox-floater').appendTo('body').hide();
    $('.mailbox[data-login='+login+']').remove()
    setClassFromBoxCount()
  }
}

function fitDelbox() {
  var fits = $(window).width() > ($('#boxlist').width() + $('#delbox-floater').width())
  $('body').toggleClass('delbox-unfit', !fits)
}

function getCookie(cname) {
  var name = cname + "=";
  var ca = document.cookie.split(';');
  for(var i = 0; i <ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0)==' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length,c.length);
    }
  }
  return "";
}