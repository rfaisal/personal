
public class ThreadCounter implements Runnable {
	Counter local;
	Counter global;
	int id;
	public ThreadCounter(int id,Counter global) {
		this.global=global;
		this.id=id;
		local= new Counter();
		// TODO Auto-generated constructor stub
	}
	public void count(){
		local.count++;
		synchronized(global){
			global.count++;
		}
		System.out.println("From Thread "+id+", local="+local.count+" ,global="+global.count);
	}
	@Override
	public void run() {
		while(local.count<100){
			count();
			try {
				synchronized(this){
				wait(10);
				}
			} catch (InterruptedException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
		
	}

}
