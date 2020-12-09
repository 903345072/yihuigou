<template>

  <div>
    <img  src="../../assets/images/call_back.png" onclick="javascript:history.go(-1)" style="width: .5rem;height:.5rem; margin-left:0.3rem;margin-top:0.2rem">

    <div class="personal" style="margin-top:50px">
		    <form action="dorecharge" id="with-drawForm" method="post">
			
				<div class="boxflex1 mt10 clearfloat">
			        <div class="withdrawal-name">收款账号：</div>
			        <div class="withdrawal-con">
			            <div class="form-group field-userwithdraw-amount required">
                            {{bank_code}}
						</div>        
					</div>
			    </div>
			    <div class="boxflex1 none clearfloat">
			        <div class="withdrawal-name">收款人：</div>
			        <div class="withdrawal-con">
                        <div class="form-group field-userwithdraw-amount required">
							{{bank_user}}
                        </div>          
					</div>
			    </div>
			    <div style="" class="boxflex1 none clearfloat">
				<div class="withdrawal-name">开户行：</div>
				<div class="withdrawal-con">
					<div class="form-group field-userwithdraw-amount required">
						{{bank_name}}
					</div>
				</div>
			</div>
				<div class="boxflex1 none clearfloat">
					<div class="withdrawal-name">充值金额:</div>
					<div class="withdrawal-con">

						<div class="form-group field-userwithdraw-amount required">
						{{money}}						</div>
					</div>
				</div>


			
				 <div style="width: 100%;display:flex;justify-content: center;margin-top: 5%">
					 <button id="submitBtn" @click="apply" type="button"  style="text-align: center;background: #fe5153;color: white;border: none">已转账成功，提交充值申请</button>
				 </div>
			    
			</form>
		</div>
  </div>
</template>
<script>
  import sendVerifyCode from "@mixins/SendVerifyCode";
  import {
    login,
    loginMobile,
    registerVerify,
    register,
    getCodeApi,
    getBankInfo,
    applyRecharge
  } from "@api/user";
  import attrs, { required, alpha_num, chs_phone,is_agreed } from "@utils/validate";
  import { validatorDefaultCatch } from "@utils/dialog";
  import { getLogo } from "@api/public";
  import cookie from "@utils/store/cookie";
  import { VUE_APP_API_URL } from "@utils";
  const BACK_URL = "login_back_url";

  export default {
    name: "Login",
    mixins: [sendVerifyCode],
    data: function() {
      return {
        navList: ["账号登录"],
        current: 0,
        account: "",
        password: "",
        captcha: "",
        formItem: 1,
        type: "login",
        name: "",
        logoUrl: "",
        keyCode: "",
        codeUrl: "",
        codeVal: "",
        invite_code: "",
        isShowCode: false,
        is_agree:false,
        money:0,
        bank_user:"",
        bank_name:"",
        bank_code:""
        
      };
    },
    mounted: function() {
      this.money = this.$route.params.money;
      this.getBank()
      this.getCode();
      this.getLogoImage();
      this.invite_code = this.getQueryVariable('code')?this.getQueryVariable('code'):'';

      if (this.getQueryVariable('code')){
        this.formItem = 0;
      }
      if (this.getQueryVariable('item')){
        this.formItem = 0;
      }
    },
    methods: {

      apply:function(){
          var that = this;
            applyRecharge({money:that.money})
                .then(res => {
                 this.$dialog.success("申请成功");
                 this.$router.push({
            path: `/`,
        })
                })
                .catch(res => {
                  this.$dialog.error(res.msg);
                }); 
      }, 
      handleDisabled:function(){
        this.is_agree = !this.is_agree;
        if(this.is_agree===true){
          this.is_agree =  true;
        }
        else{
          this.is_agree =  false;
        }
        console.log(this.is_agree)
      },
      getQueryVariable(variable) {
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i=0;i<vars.length;i++) {
          var pair = vars[i].split("=");
          if(pair[0] === variable){return pair[1];}
        }
        return false;
      },
      again() {
        this.codeUrl =
                VUE_APP_API_URL +
                "/sms_captcha?" +
                "key=" +
                this.keyCode +
                Date.parse(new Date());
      },
      getBank(){
          var that = this
         getBankInfo()
                .then(res => {
                  that.bank_code = res.data.bank_code;
                  that.bank_name = res.data.bank_name;
                  that.bank_user = res.data.bank_user;
                })
                .catch(res => {
                  this.$dialog.error(res.msg);
                }); 
      },
      getCode() {
        getCodeApi()
                .then(res => {
                  this.keyCode = res.data.key;
                })
                .catch(res => {
                  this.$dialog.error(res.msg);
                });
      },
      async getLogoImage() {
        let that = this;
        getLogo(2).then(res => {
          that.logoUrl = res.data.logo_url;
        });
      },
      async loginMobile() {
        var that = this;
        const { account, captcha } = that;
        try {
          await that
                  .$validator({
                    account: [
                      required(required.message("手机号码")),
                      chs_phone(chs_phone.message())
                    ],
                    captcha: [
                      required(required.message("验证码")),
                      alpha_num(alpha_num.message("验证码"))
                    ]
                  })
                  .validate({ account, captcha });
        } catch (e) {
          return validatorDefaultCatch(e);
        }
        loginMobile({
          phone: that.account,
          captcha: that.captcha,
          spread: cookie.get("spread")
        })
                .then(res => {
                  let data = res.data;
                  let expires_time = data.expires_time.substring(0, 19);
                  expires_time = expires_time.replace(/-/g, "/");
                  expires_time = new Date(expires_time).getTime() - 28800000;
                  // const expires_time = new Date(data.expires_time).getTime() / 1000;
                  const datas = {
                    token: data.token,
                    expires_time: expires_time
                  };

                  this.$store.commit("LOGIN", datas);
                  const backUrl = cookie.get(BACK_URL) || "/";
                  cookie.remove(BACK_URL);
                  that.$router.replace({ path: backUrl });
                })
                .catch(res => {
                  that.$dialog.error(res.msg);
                });
      },
      async register() {
        var that = this;
        const { account, captcha, password,invite_code} = that;
        if (!this.is_agree){
          that.$dialog.error("请同意用户注册协议")
          return;
        }
        try {
          await that
                  .$validator({

                    account: [
                      required(required.message("手机号码")),
                      chs_phone(chs_phone.message())
                    ],
                    captcha: [
                      required(required.message("验证码")),
                      alpha_num(alpha_num.message("验证码"))
                    ],
                    password: [
                      required(required.message("密码")),
                      attrs.range([6, 16], attrs.range.message("密码")),
                      alpha_num(alpha_num.message("密码"))
                    ]
                  })
                  .validate({ account, captcha, password});
        } catch (e) {
          return validatorDefaultCatch(e);
        }
        register({
          invite_code: that.invite_code,
          account: that.account,
          captcha: that.captcha,
          password: that.password,
          spread: cookie.get("spread"),
          name: this.name
        })
                .then(res => {
                  that.$dialog.success(res.msg);
                  that.formItem = 1;
                })
                .catch(res => {
                  that.$dialog.error(res.msg);
                });
      },
      async code() {
        var that = this;
        const { account } = that;
        try {
          await that
                  .$validator({
                    account: [
                      required(required.message("手机号码")),
                      chs_phone(chs_phone.message())
                    ]
                  })
                  .validate({ account });
        } catch (e) {
          return validatorDefaultCatch(e);
        }
        if (that.formItem == 2) that.type = "register";
        await registerVerify({
          phone: that.account,
          type: "register",
          key: that.keyCode,
          code: that.codeVal
        })
                .then(res => {
                  that.$dialog.success(res.msg);
                  that.sendCode();
                })
                .catch(res => {
                  if (res.data.status === 402) {
                    that.codeUrl = `${VUE_APP_API_URL}/sms_captcha?key=${that.keyCode}`;
                    that.isShowCode = true;
                  }
                  that.$dialog.error(res.msg);
                });
      },
      navTap: function(index) {
        this.current = index;
      },
      async submit() {
        const { account, password, codeVal, invite_code } = this;
        try {
          await this.$validator({
            account: [
              required(required.message("账号")),
              attrs.range([5, 16], attrs.range.message("账号")),
              alpha_num(alpha_num.message("账号"))
            ],
            password: [
              required(required.message("密码")),
              attrs.range([6, 16], attrs.range.message("密码")),
              alpha_num(alpha_num.message("密码"))
            ],
            codeVal: this.isShowCode
                    ? [
                      required(required.message("验证码")),
                      attrs.length(4, attrs.length.message("验证码")),
                      alpha_num(alpha_num.message("验证码"))
                    ]
                    : []
          }).validate({ account, password, codeVal });
        } catch (e) {
          return validatorDefaultCatch(e);
        }

        login({ account, password, code: codeVal })
                .then(({ data }) => {
                  let expires_time = data.expires_time.substring(0, 19);
                  expires_time = expires_time.replace(/-/g, "/");
                  expires_time = new Date(expires_time).getTime() - 28800000;
                  const datas = {
                    token: data.token,
                    expires_time: expires_time
                  };
                  localStorage.setItem("LOGIN",JSON.stringify(datas));
                  this.$store.commit("LOGIN", datas);
                  const backUrl = cookie.get(BACK_URL) || "/";
                  cookie.remove(BACK_URL);
                  this.$router.replace({ path: backUrl });
                  //  let newTime = Math.round(new Date() / 1000);
                  // this.$store.commit("LOGIN", data.token, dayjs(data.expires_time))213123
                })
                .catch(e => {
                  this.$dialog.error(e.msg);
                });
      }
    }
  };
</script>
<style scoped>
  .code img {
    width: 100%;
    height: 100%;
  }
  
  .personal {
    max-width: 640px;
    min-width: 320px;
    width: 100%;
    margin: 0 auto;
    padding: 0;
}

.withdrew_body .boxflex1 {
    padding: 5px 10px;
    border-top: 1px solid #ccc;
    border-bottom: 1px solid #ccc;
    background: #fff;
    font-size: 14px;
}

.withdrawal-name {
    text-align: right;
    padding-right: 20px;
}
.withdrawal-name {
    width: 25%;
    float: left;
    font-size: 12px;
    color: #333;
    line-height: 28px;
}
.withdrawal-con {
    width: 75%;
    float: left;
}

.required {
    line-height: 30px;
    height: 30px;
}

.mui-btn, button, input[type="button"], input[type="reset"], input[type="submit"] {
    font-size: 14px;
    font-weight: 400;
    line-height: 1.42;
    position: relative;
    display: inline-block;
    margin-bottom: 0;
    padding: 6px 12px;
    cursor: pointer;
    -webkit-transition: all;
    transition: all;
        transition-duration: 0s;
        transition-timing-function: ease;
    -webkit-transition-timing-function: linear;
    transition-timing-function: linear;
    -webkit-transition-duration: .2s;
    transition-duration: .2s;
    text-align: center;
    vertical-align: top;
    white-space: nowrap;
    color: #333;
    border: 1px solid #ccc;
    border-radius: 3px;
    border-top-left-radius: 3px;
    border-top-right-radius: 3px;
    border-bottom-right-radius: 3px;
    border-bottom-left-radius: 3px;
    background-clip: padding-box;
}
  
</style>
