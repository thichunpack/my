if(result.st==='ok'){
    document.getElementById('display_bal').innerText = result.new;

    // ✅ đảm bảo đồng bộ tuyệt đối
    setTimeout(refreshBalance, 300);

    currentAmount=0;
    document.getElementById('amountBox').innerText='0';
    closePopup();
}